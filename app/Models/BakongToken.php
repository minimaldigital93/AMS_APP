<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The Bakong Open API access token for this installation's integrator identity.
 *
 * Lifecycle, per the NBC document:
 *
 *   POST /v1/request_token {email, organization, project}  → emails a 20-char code
 *   POST /v1/verify        {code}                          → {data:{token}}
 *   POST /v1/renew_token   {email}                         → {data:{token}}
 *
 * Deliberately NOT account-scoped: the token belongs to the installation, not
 * to a customer account, exactly as Subscription is kept out of
 * BelongsToAccount.
 *
 * EXPIRY IS READ FROM THE JWT, NEVER ASKED FOR. The token states its own `exp`
 * in plain sight, so spending a metered request to learn it would be paying
 * Bakong for information already in hand — and would do so precisely when the
 * allowance is most likely to be under pressure.
 */
class BakongToken extends Model
{
    protected $fillable = [
        'email',
        'organization',
        'project',
        'token',
        'expires_at',
        'verified_at',
        'renewed_at',
    ];

    /**
     * Encrypted at rest: a database dump must not hand anyone a live credential.
     * Hidden as well, so it cannot slip into a JSON response or a log line that
     * serialises the model.
     */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    /** The token row for the configured integrator email, if one exists. */
    public static function current(): ?self
    {
        $email = (string) config('bakong.integrator.email');

        if ($email === '') {
            return null;
        }

        return static::query()->where('email', $email)->first();
    }

    /**
     * Can this token be put in an Authorization header right now?
     *
     * An expired token is treated as absent rather than sent anyway: Bakong
     * charges a 401 exactly like a successful call, so offering a token we can
     * already see is dead spends the allowance to be told what we knew.
     */
    public function isUsable(): bool
    {
        if (blank($this->token) || $this->verified_at === null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Is it close enough to expiry to renew?
     *
     * A token with no readable expiry returns false: renewing on a schedule we
     * cannot justify would be a standing metered request, and a genuinely dead
     * token surfaces as a 401 that trips the failure backoff anyway.
     */
    public function needsRenewal(): bool
    {
        if ($this->expires_at === null || blank($this->token)) {
            return false;
        }

        return $this->expires_at->isBefore(
            Carbon::now()->addDays(max(0, (int) config('bakong.token_renew_days', 7)))
        );
    }

    /**
     * Read the `exp` claim out of a JWT without verifying its signature.
     *
     * We are not authenticating the token here — Bakong just handed it to us
     * over TLS and is the only party that can validate it. We are reading a
     * scheduling hint, so an unreadable or malformed token simply yields null
     * and the caller falls back to "expiry unknown".
     */
    public static function expiryFromJwt(?string $jwt): ?Carbon
    {
        if (blank($jwt)) {
            return null;
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        $claims = json_decode($payload, true);

        if (! is_array($claims) || ! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $claims['exp']);
    }

    /**
     * Read the integrator EMAIL out of a JWT, without verifying its signature.
     *
     * This is the address NBC has on file, and it matters for exactly one
     * reason: `renew_token` sends BAKONG_EMAIL upstream and nothing else. A
     * local typo costs nothing day to day — `BakongToken::current()` looks the
     * row up by the same configured string that `store()` stamped on it, so the
     * two always agree with each other no matter how either is spelled — and
     * then silently fails to renew ~90 days later, which reads as "payments
     * stopped working" long after anyone would connect it to a missing letter.
     *
     * The claim's NAME is not documented, so this looks for the first
     * email-shaped string anywhere in the claims rather than assuming one. A
     * token that carries none simply yields null and the caller says "could not
     * be determined" — never a mismatch it cannot substantiate.
     */
    public static function emailFromJwt(?string $jwt): ?string
    {
        if (blank($jwt)) {
            return null;
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        $claims = json_decode($payload, true);

        if (! is_array($claims)) {
            return null;
        }

        return static::findEmail($claims);
    }

    /**
     * First email-shaped leaf in an arbitrarily nested claim set.
     *
     * @param  array<mixed>  $claims
     */
    private static function findEmail(array $claims): ?string
    {
        foreach ($claims as $value) {
            if (is_array($value)) {
                if ($found = static::findEmail($value)) {
                    return $found;
                }

                continue;
            }

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * How the token may be described in a log, a command's output or a
     * diagnostics report — never the token itself.
     */
    public function fingerprint(): string
    {
        if (blank($this->token)) {
            return 'none';
        }

        return substr(hash('sha256', (string) $this->token), 0, 12);
    }
}
