<?php

use App\Models\BakongApiCall;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongQuotaLedger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE TWO GUARANTEES THAT ONLY HOLD IF THEY HOLD UNDER CONCURRENCY.
 *
 * A daily ceiling that yields when several workers arrive at once, or a
 * cooldown two processes can both pass, does nothing at all in exactly the
 * conditions it exists for — and the failure is invisible until the allowance
 * is gone. Neither can be proved by calling a method twice in a row.
 *
 * So these race REAL PROCESSES: pcntl_fork, a shared file cache for the locks
 * and a shared SQLite FILE for the ledger, with every worker released at the
 * same wall-clock instant. Skipped where pcntl is unavailable.
 *
 * The KHQRPay integration learned both of these the expensive way. Its cooldown
 * was check-then-act and was only written once the answer came back, so eight
 * simultaneous processes made eight metered requests about one transaction; and
 * its budget reservation caught every exception — including the lock timing out
 * under contention — then counted the call and allowed it, so the ceiling gave
 * way under precisely the load it was written for.
 */

/**
 * Run $work in $workers forked processes, all released together, sharing a file
 * cache and a file-backed SQLite ledger.
 *
 * @return array{allowed:int, blocked:array<string,int>}
 */
function bakongRace(int $workers, Closure $work, ?Closure $beforeFork = null): array
{
    // The race swaps the DEFAULT database connection and cache store so the
    // forked children share them. Both must be put back before this function
    // returns, or the next test in the process inherits a connection whose
    // RefreshDatabase transaction was never opened on it.
    $originalConnection = DB::getDefaultConnection();
    $originalCacheStore = config('cache.default');

    $dir = sys_get_temp_dir().'/bakong-race-'.bin2hex(random_bytes(6));
    mkdir($dir.'/cache', 0777, true);
    $dbFile = $dir.'/ledger.sqlite';
    touch($dbFile);

    // The parent still reads the shared state after the race, so clean up when
    // the parent ends. Children are SIGKILLed and never run this.
    register_shutdown_function(function () use ($dir) {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($dir);
    });

    config()->set('cache.stores.bakong-race', ['driver' => 'file', 'path' => $dir.'/cache', 'lock_path' => $dir.'/cache']);
    Cache::setDefaultDriver('bakong-race');

    // WAL + a busy timeout so concurrent writers queue instead of erroring.
    // The budget reservation is serialised by the cache lock anyway; this is
    // for the best-effort writes that happen outside it.
    config()->set('database.connections.bakong-race', [
        'driver' => 'sqlite',
        'database' => $dbFile,
        'prefix' => '',
        'foreign_key_constraints' => false,
        'journal_mode' => 'wal',
        'busy_timeout' => 10000,
    ]);
    DB::setDefaultConnection('bakong-race');
    DB::purge('bakong-race');

    Schema::connection('bakong-race')->create('bakong_api_calls', function ($table) {
        $table->id();
        $table->date('called_on');
        $table->string('endpoint', 64);
        $table->string('reason', 32);
        $table->string('target', 16)->default('platform');
        // Mirrors the real schema: whose allowance the call was charged to.
        // Null is the platform's own budget.
        $table->unsignedBigInteger('account_id')->nullable();
        $table->unsignedBigInteger('khqr_payment_id')->nullable();
        $table->boolean('allowed');
        $table->string('blocked_reason', 32)->nullable();
        $table->unsignedSmallInteger('http_status')->nullable();
        $table->integer('response_code')->nullable();
        $table->integer('error_code')->nullable();
        $table->string('outcome', 16)->nullable();
        $table->unsignedInteger('duration_ms')->nullable();
        $table->timestamps();
    });

    config()->set('logging.default', 'null');

    if ($beforeFork !== null) {
        $beforeFork();
    }

    $startAt = microtime(true) + 0.3;
    $pids = [];

    for ($i = 0; $i < $workers; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('fork failed');
        }

        if ($pid === 0) {
            try {
                // A PDO handle must never be shared across a fork: the children
                // would corrupt each other's protocol state on the same socket.
                DB::purge('bakong-race');

                while (microtime(true) < $startAt) {
                    usleep(500);
                }

                $work($i);
            } catch (\Throwable $e) {
                file_put_contents($dir.'/errors', $e->getMessage()."\n", FILE_APPEND | LOCK_EX);
            }

            posix_kill(posix_getpid(), SIGKILL);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    DB::purge('bakong-race');

    $result = [
        'allowed' => BakongApiCall::on('bakong-race')->where('allowed', true)->count(),
        'blocked' => BakongApiCall::on('bakong-race')->where('allowed', false)
            ->selectRaw('blocked_reason, COUNT(*) as total')
            ->groupBy('blocked_reason')->pluck('total', 'blocked_reason')
            ->map(fn ($n) => (int) $n)->all(),
    ];

    // Hand the process back exactly as it was found. Never purge the original
    // connection: RefreshDatabase's open transaction lives on it.
    DB::purge('bakong-race');
    DB::setDefaultConnection($originalConnection);
    Cache::setDefaultDriver($originalCacheStore);

    return $result;
}

beforeEach(function () {
    if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
        $this->markTestSkipped('pcntl/posix are needed to race real processes.');
    }
});

// ═════════════════════ the daily ceiling, under load ═════════════════════

it('never exceeds the safety budget when concurrent workers race for the last slot', function () {
    // The exact scenario from the brief: a limit of 80 with 79 already spent.
    // Two workers arrive together and both see 79 remaining slots' worth of
    // headroom if the check and the increment are not atomic.
    config()->set('bakong.daily_request_limit', 80);

    $race = bakongRace(
        workers: 8,
        work: function () {
            $ledger = new BakongQuotaLedger;
            $reservation = $ledger->reserve('platform', 'payment_verification', '/v1/check_transaction_by_md5', null);

            if (is_string($reservation)) {
                $ledger->recordBlocked('platform', 'payment_verification', '/v1/check_transaction_by_md5', null, $reservation);
            } else {
                // Hold the slot briefly, the way a real request would, so the
                // other workers arrive while this one is "in flight".
                usleep(100_000);
            }
        },
        beforeFork: function () {
            for ($i = 0; $i < 79; $i++) {
                BakongApiCall::on('bakong-race')->create([
                    'called_on' => now()->toDateString(),
                    'endpoint' => '/v1/check_transaction_by_md5',
                    'reason' => 'payment_verification',
                    'target' => 'platform',
                    'allowed' => true,
                ]);
            }
        },
    );

    // Exactly one slot was left, and exactly one worker may have it. Not two,
    // not eight — and the total must never pass the configured ceiling.
    expect($race['allowed'])->toBe(80)
        ->and($race['blocked'][BakongProviderClient::BLOCK_BUDGET] ?? 0)->toBe(7);
});

it('stops calling entirely once the ceiling is already reached', function () {
    config()->set('bakong.daily_request_limit', 80);

    $race = bakongRace(
        workers: 4,
        work: function () {
            $ledger = new BakongQuotaLedger;
            $reservation = $ledger->reserve('platform', 'payment_verification', '/v1/check_transaction_by_md5', null);

            if (is_string($reservation)) {
                $ledger->recordBlocked('platform', 'payment_verification', '/v1/check_transaction_by_md5', null, $reservation);
            }
        },
        beforeFork: function () {
            for ($i = 0; $i < 80; $i++) {
                BakongApiCall::on('bakong-race')->create([
                    'called_on' => now()->toDateString(),
                    'endpoint' => '/v1/check_transaction_by_md5',
                    'reason' => 'payment_verification',
                    'target' => 'platform',
                    'allowed' => true,
                ]);
            }
        },
    );

    expect($race['allowed'])->toBe(80)
        ->and($race['blocked'][BakongProviderClient::BLOCK_BUDGET] ?? 0)->toBe(4);
});

it('keeps each settlement target on its own allowance', function () {
    // A landlord who registers their own integrator token gets their own
    // budget. One busy target must never lock out another — which is the whole
    // reason the ceiling is per-target rather than global.
    config()->set('bakong.daily_request_limit', 2);

    $race = bakongRace(
        workers: 4,
        work: function (int $worker) {
            $ledger = new BakongQuotaLedger;
            $target = $worker % 2 === 0 ? 'platform' : 'merchant';
            $reservation = $ledger->reserve($target, 'payment_verification', '/v1/check_transaction_by_md5', null);

            if (is_string($reservation)) {
                $ledger->recordBlocked($target, 'payment_verification', '/v1/check_transaction_by_md5', null, $reservation);
            }
        },
    );

    // Two workers per target, a ceiling of two each: all four get through.
    expect($race['allowed'])->toBe(4)
        ->and($race['blocked'])->toBe([]);
});

// ══════════════════ the per-transaction cooldown, under load ══════════════════

it('makes exactly one request when several processes verify the same transaction at once', function () {
    config()->set('bakong.daily_request_limit', 0);   // the ceiling is not what we are testing
    config()->set('bakong.verify_cooldown', 60);

    $race = bakongRace(
        workers: 8,
        work: function () {
            $ledger = new BakongQuotaLedger;

            // Every worker asks about the SAME transaction at the same instant
            // — a payer with two tabs open, a queue worker and the reconcile
            // run, which is the ordinary case rather than a contrived one.
            if (! $ledger->claimVerifySlot('SUB-RACE-1')) {
                $ledger->recordBlocked('platform', 'payment_verification', '/v1/check_transaction_by_md5', null, BakongProviderClient::BLOCK_COOLDOWN);

                return;
            }

            $reservation = $ledger->reserve('platform', 'payment_verification', '/v1/check_transaction_by_md5', null);

            if (! is_string($reservation)) {
                usleep(200_000);   // a slow gateway: the others arrive mid-flight
            }
        },
    );

    expect($race['allowed'])->toBe(1)
        ->and($race['blocked'][BakongProviderClient::BLOCK_COOLDOWN] ?? 0)->toBe(7);
});

it('lets different transactions through the cooldown independently', function () {
    config()->set('bakong.daily_request_limit', 0);
    config()->set('bakong.verify_cooldown', 60);

    $race = bakongRace(
        workers: 6,
        work: function (int $worker) {
            $ledger = new BakongQuotaLedger;

            // The cooldown throttles one transaction, never the gateway as a
            // whole: six payers checking out at once must not queue behind
            // each other.
            if (! $ledger->claimVerifySlot('SUB-RACE-'.$worker)) {
                $ledger->recordBlocked('platform', 'payment_verification', '/v1/check_transaction_by_md5', null, BakongProviderClient::BLOCK_COOLDOWN);

                return;
            }

            $ledger->reserve('platform', 'payment_verification', '/v1/check_transaction_by_md5', null);
        },
    );

    expect($race['allowed'])->toBe(6)
        ->and($race['blocked'])->toBe([]);
});
