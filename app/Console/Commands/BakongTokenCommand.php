<?php

namespace App\Console\Commands;

use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTokenService;
use Illuminate\Console\Command;

/**
 * Operator command for the Bakong access token.
 *
 * Token issuance cannot be automated: Bakong emails a 20-character code and a
 * human has to read it out of a mailbox. That human step is the reason this is
 * a command rather than a settings page — there is nothing for a web form to do
 * that a terminal does not do more safely, and the token must never pass
 * through a browser, a flash message or a form field on its way to storage.
 *
 *   php artisan bakong:token status
 *   php artisan bakong:token request
 *   php artisan bakong:token verify --code=XXXXXXXXXXXXXXXXXXXX
 *   php artisan bakong:token renew
 *   php artisan bakong:token renew --if-due      (what the scheduler runs)
 *
 * `status` is free and offline. Every other action spends exactly one metered
 * request and says so before it does.
 */
class BakongTokenCommand extends Command
{
    protected $signature = 'bakong:token
                            {action : status | request | verify | import | renew}
                            {--code= : the 20-character code Bakong emailed (verify only)}
                            {--if-due : renew only inside the renewal window (renew only)}
                            {--force : skip the confirmation on actions that spend a request}';

    protected $description = 'Issue, verify, renew or inspect the Bakong Open API access token';

    public function handle(BakongTokenService $tokens): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['status', 'request', 'verify', 'import', 'renew'], true)) {
            $this->error("Unknown action [{$action}]. Use status, request, verify, import or renew.");

            return self::FAILURE;
        }

        // status costs nothing and must keep working when everything else is
        // refused — it is the report an operator reads to find out WHY.
        if ($action === 'status') {
            return $this->showStatus($tokens);
        }

        // import contacts nobody, so it works before the switch is ever turned
        // on — which is the order an operator actually does this in: install the
        // credential, confirm it offline, then enable.
        if ($action === 'import') {
            return $this->import($tokens);
        }

        if (! BakongProviderClient::featureEnabled()) {
            $this->warn('Bakong is switched off (BAKONG_API_ENABLED / BAKONG_API_BASE_URL).');
            $this->line('No request was made. Nothing was spent.');

            return self::FAILURE;
        }

        return match ($action) {
            'request' => $this->request($tokens),
            'verify' => $this->verify($tokens),
            'renew' => $this->renew($tokens),
        };
    }

    private function showStatus(BakongTokenService $tokens): int
    {
        $status = $tokens->status();

        $this->newLine();
        $this->line('<options=bold>Bakong access token</>');
        $this->newLine();

        $this->table([], [
            ['Feature enabled', BakongProviderClient::featureEnabled() ? '<fg=green>yes</>' : '<fg=yellow>no</>'],
            ['Requests permitted', BakongProviderClient::providerCallsPermitted() ? '<fg=green>yes</>' : '<fg=yellow>no</>'],
            ['Base URL', BakongProviderClient::baseUrl() !== null
                ? BakongProviderClient::baseUrlForDisplay()
                : '<fg=yellow>'.BakongProviderClient::baseUrlForDisplay().'</>'],
            ['Integrator email', $status['email'] ?: '<fg=yellow>not set</>'],
            ['Matches the token', match ($status['email_matches']) {
                true => '<fg=green>yes</>',
                false => '<fg=red>NO — renewal will fail in ~90 days</>',
                default => '<fg=gray>token carries no email claim</>',
            }],
            ['Registered', $status['registered'] ? 'yes' : '<fg=yellow>no — run: bakong:token request</>'],
            ['Verified', $status['verified'] ? 'yes' : '<fg=yellow>no — run: bakong:token verify --code=...</>'],
            ['Usable now', $status['usable'] ? '<fg=green>yes</>' : '<fg=red>no</>'],
            ['Expires', $status['expires_at'] ?? 'unknown'],
            ['Renewal due', $status['needs_renewal'] ? '<fg=yellow>yes</>' : 'no'],
            ['Last renewed', $status['renewed_at'] ?? 'never'],
            // Never the token. A fingerprint is enough to tell two reports
            // apart without putting a live credential on anyone's screen or in
            // their scrollback.
            ['Fingerprint', $status['fingerprint']],
        ]);

        $this->newLine();

        // Said in full, not as a diff: the failure mode this catches is a
        // single wrong letter, which is exactly what the eye slides over.
        if ($status['email_matches'] === false) {
            $this->warn('BAKONG_EMAIL does not match the address this token was issued to.');
            $this->line('  .env says:      '.$status['email']);
            $this->line('  the token says: '.$status['token_email']);
            $this->newLine();
            $this->line('Payments work either way today — the token is looked up locally by');
            $this->line('whichever string .env holds. But renew_token sends .env\'s value to NBC,');
            $this->line('so renewal fails silently in ~90 days. Fix .env to the token\'s address,');
            $this->line('then re-import so the stored row is keyed to it as well.');
            $this->newLine();
        }

        $this->line('This report is offline — it made no Bakong request.');

        return self::SUCCESS;
    }

    private function request(BakongTokenService $tokens): int
    {
        if (! $this->confirmSpend('Register this integrator and ask Bakong to email a verification code')) {
            return self::FAILURE;
        }

        return $this->report($tokens->requestCode(), 'Check the mailbox for '.config('bakong.integrator.email').', then run: bakong:token verify --code=...');
    }

    private function verify(BakongTokenService $tokens): int
    {
        $code = (string) $this->option('code');

        if ($code === '') {
            // Asked rather than echoed on the command line: a code in a
            // shell history is a credential in a shell history.
            $code = (string) $this->secret('Paste the 20-character code Bakong emailed');
        }

        if (! $this->confirmSpend('Exchange this code for an access token')) {
            return self::FAILURE;
        }

        return $this->report($tokens->verifyCode($code), 'The token is stored encrypted. Run "bakong:token status" to confirm.');
    }

    /**
     * Install a token the operator already holds. Costs nothing, so there is no
     * spend confirmation — and it is prompted for with secret() rather than
     * taken on the command line, because a token in a shell history is a
     * credential in a shell history.
     */
    private function import(BakongTokenService $tokens): int
    {
        $token = (string) $this->option('code');

        if ($token === '') {
            $token = (string) $this->secret('Paste the Bakong access token (it will not be echoed)');
        }

        return $this->report(
            $tokens->importToken($token),
            'Nothing was sent to Bakong. Confirm with "bakong:diagnose", then "--live" when you are ready to spend one request.',
        );
    }

    private function renew(BakongTokenService $tokens): int
    {
        // --if-due is the scheduler's entry point and answers "no" on almost
        // every run, so it never asks for confirmation and never spends a
        // request it does not need.
        if ($this->option('if-due')) {
            return $this->report($tokens->renewIfDue(), null);
        }

        if (! $this->confirmSpend('Renew the Bakong access token now')) {
            return self::FAILURE;
        }

        return $this->report($tokens->renew(), null);
    }

    /**
     * Every action but `status` costs one request out of a daily allowance of
     * roughly a hundred, so it is stated plainly and confirmed — the same shape
     * as khqr:expire-abandoned's confirmation, and for the same reason.
     */
    private function confirmSpend(string $what): bool
    {
        if ($this->option('force') || ! $this->input->isInteractive()) {
            return true;
        }

        $this->newLine();
        $this->line($what.'.');
        $this->warn('This spends 1 request from today’s Bakong allowance.');

        return $this->confirm('Continue?', true);
    }

    /** @param array{ok: bool, message: string, blocked: ?string} $result */
    private function report(array $result, ?string $next): int
    {
        $this->newLine();

        if (! $result['ok']) {
            $this->error($result['message']);

            if ($result['blocked'] !== null) {
                $this->line('Nothing was sent, so nothing was spent. Run "bakong:usage" to see today’s ledger.');
            }

            return self::FAILURE;
        }

        $this->info($result['message']);

        if ($next !== null) {
            $this->newLine();
            $this->line($next);
        }

        return self::SUCCESS;
    }
}
