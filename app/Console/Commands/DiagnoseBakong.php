<?php

namespace App\Console\Commands;

use App\Models\BakongApiCall;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongQuotaLedger;
use App\Services\Bakong\BakongTokenService;
use Illuminate\Console\Command;

/**
 * Can this installation take a Bakong payment right now, and if not, which part
 * is wrong?
 *
 * OFFLINE BY DEFAULT. A report must not spend the allowance it is reporting on
 * — and the moment anyone runs this is precisely the moment the allowance is
 * likely to be under pressure. Everything that can be answered locally is:
 * the feature switch, the base URL, the integrator identity, the token and its
 * expiry (read from the JWT), today's spend, and any backoff in force.
 *
 * --live adds ONE request: a check_bakong_account call against the configured
 * account id, which is the cheapest question that proves the token is accepted
 * and the account exists. It is the only check here that cannot be answered
 * from local state, and it says so before it spends anything.
 *
 * This exists as a command as well as a report because the failure it diagnoses
 * can lock the operator out of the UI: an account with no active subscription
 * and a gateway that will not take payment has nowhere in the app to stand.
 */
class DiagnoseBakong extends Command
{
    protected $signature = 'bakong:diagnose
                            {--live : also make ONE request to confirm the token is accepted}';

    protected $description = 'Check whether this installation can take a Bakong payment (offline unless --live)';

    public function handle(BakongTokenService $tokens, BakongQuotaLedger $ledger): int
    {
        $checks = [];
        $healthy = true;

        // ---- configuration (free) ----
        $enabled = (bool) config('bakong.enabled');
        $baseUrl = BakongProviderClient::baseUrl();
        $accountId = (string) config('bakong.account_id');

        $checks[] = $this->check('Feature switch', $enabled, $enabled
            ? 'BAKONG_API_ENABLED=true'
            : 'BAKONG_API_ENABLED is false — nothing contacts Bakong', 'Set BAKONG_API_ENABLED=true in .env, then `php artisan config:cache`.');

        // The value is only shown when it is a valid URL. When it is not, the
        // likeliest cause is a credential pasted into the wrong variable, and
        // echoing it here would put that credential in a scrollback.
        $checks[] = $this->check('API base URL', $baseUrl !== null,
            BakongProviderClient::baseUrlForDisplay(),
            'NBC supplies this at integrator onboarding; it is deliberately not guessed. It must be a full https:// URL — if you pasted a token here, rotate that token.');

        $checks[] = $this->check('Payout account', $accountId !== '', $accountId !== ''
            ? $accountId
            : 'not set', 'Set BAKONG_ACCOUNT_ID — without it no subscription QR can be built.');

        // ---- token (free: expiry comes out of the JWT) ----
        $token = $tokens->status();

        $checks[] = $this->check('Integrator identity', $token['configured'], $token['email'] ?: 'not set',
            'Set BAKONG_EMAIL, BAKONG_ORGANIZATION and BAKONG_PROJECT.');

        $checks[] = $this->check('Access token', $token['usable'],
            $token['usable']
                ? 'valid until '.($token['expires_at'] ?? 'unknown').' ('.$token['fingerprint'].')'
                : ($token['registered'] ? 'registered but not usable' : 'not registered'),
            $token['registered']
                ? 'Run `php artisan bakong:token renew`.'
                : 'Run `php artisan bakong:token request`, then verify with the emailed code.');

        if ($token['needs_renewal']) {
            $this->warn('The token is inside its renewal window; the daily scheduled run will renew it.');
        }

        // ---- allowance and backoffs (free) ----
        $limit = $ledger->limit();
        $spent = $ledger->spentToday('platform');
        $exhausted = $ledger->exhausted('platform');

        $checks[] = $this->check("Today's allowance", ! $exhausted,
            $limit > 0 ? "{$spent} / {$limit} spent" : "{$spent} spent (no ceiling configured)",
            'The ceiling resets at midnight. `bakong:usage` shows what spent it.');

        $backoff = $ledger->activeBackoff('platform');

        $checks[] = $this->check('Provider backoff', $backoff === null,
            $backoff === null ? 'none' : 'in force until '.$backoff['until']->toIso8601String().' — '.$backoff['why'],
            'A backoff clears itself, and storing a fresh token clears it immediately.');

        // ---- the one live check ----
        if ($this->option('live') && $baseUrl === null) {
            // Refusing locally rather than letting the client refuse: with no
            // endpoint there is nothing to check, and the operator has already
            // been told which line to fix.
            $checks[] = $this->check('Live token check', false,
                'skipped — there is no valid API base URL to call', 'Fix the base URL above first. Nothing was sent.');
        } elseif ($this->option('live')) {
            $checks[] = $this->liveCheck($accountId);
        } else {
            $checks[] = [
                'label' => 'Live token check',
                'ok' => null,
                'detail' => 'skipped — pass --live to spend 1 request confirming the token is accepted',
                'remedy' => null,
            ];
        }

        foreach ($checks as $check) {
            if ($check['ok'] === false) {
                $healthy = false;
            }
        }

        $this->render($checks, $healthy);

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /**
     * check_bakong_account is the cheapest question that proves the token is
     * accepted: responseCode 0 means the account exists, 1 means it does not,
     * and either answer means our credential was honoured. A 401 or a local
     * refusal means it was not.
     */
    private function liveCheck(string $accountId): array
    {
        if ($accountId === '') {
            return $this->check('Live token check', false, 'no account id to check', 'Set BAKONG_ACCOUNT_ID first.');
        }

        $result = (new BakongProviderClient)->call(
            reason: BakongProviderClient::REASON_MANUAL_DIAGNOSTIC,
            endpoint: BakongProviderClient::EP_CHECK_ACCOUNT,
            payload: ['accountId' => $accountId],
        );

        if ($result->wasBlocked()) {
            return $this->check('Live token check', false,
                'refused locally before sending: '.$result->blockedReason,
                'Nothing was spent. The gate above that failed is the one to fix.');
        }

        if (! $result->hasResponse()) {
            return $this->check('Live token check', false, 'Bakong could not be reached',
                'A network failure says nothing about the token; try again.');
        }

        if ($result->status() === 401 || $result->status() === 403 || $result->errorCode() === 6) {
            return $this->check('Live token check', false, 'HTTP '.$result->status().' — token rejected',
                'Run `php artisan bakong:token renew`.');
        }

        // responseCode 1 here means "account does not exist", which is still a
        // successful authentication — and a finding worth reporting separately,
        // because a payout account Bakong does not know collects nothing.
        if ($result->responseCode() === 1) {
            return $this->check('Live token check', false,
                'token accepted, but Bakong does not know account '.$accountId,
                'Check BAKONG_ACCOUNT_ID against the Bakong account that should receive subscription payments.');
        }

        return $this->check('Live token check', true, 'token accepted; account '.$accountId.' exists', null);
    }

    private function check(string $label, ?bool $ok, string $detail, ?string $remedy): array
    {
        return compact('label', 'ok', 'detail', 'remedy');
    }

    private function render(array $checks, bool $healthy): void
    {
        $this->newLine();
        $this->line('<options=bold>Bakong Open API diagnostics</>');
        $this->newLine();

        $rows = [];

        foreach ($checks as $check) {
            $rows[] = [
                match ($check['ok']) {
                    true => '<fg=green>ok</>',
                    false => '<fg=red>fail</>',
                    null => '<fg=gray>—</>',
                },
                $check['label'],
                $check['detail'],
            ];

            // Every failing check carries its own remedy, because "Bakong
            // refused" hides several different jobs done in different places by
            // different people — add a credential, wait out an allowance, renew
            // a token, correct an account id.
            if ($check['ok'] === false && $check['remedy']) {
                $rows[] = ['', '', '<fg=cyan>→ '.$check['remedy'].'</>'];
            }
        }

        $this->table(['', 'Check', 'Detail'], $rows);

        $this->newLine();
        $this->line($healthy
            ? '<fg=green>This installation can take a Bakong payment.</>'
            : '<fg=red>This installation cannot take a Bakong payment yet.</>');

        if (! $this->option('live')) {
            $this->line('Offline report — no Bakong request was made. Add --live to confirm the token.');
        }

        $this->line('Requests recorded today: '.BakongApiCall::spentOn('platform').' (see `bakong:usage`)');
    }
}
