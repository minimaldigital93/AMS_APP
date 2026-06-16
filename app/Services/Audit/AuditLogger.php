<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes append-only audit entries. Resolve via the container and call record();
 * the actor defaults to the authenticated user (null = a system action such as a
 * webhook or cron). Never throws into the caller — an audit-write failure must
 * not roll back the money action it is recording.
 */
class AuditLogger
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function record(string $action, ?Model $auditable = null, array $context = [], $actor = null): ?AuditLog
    {
        $actor ??= Auth::user();

        try {
            return AuditLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_role' => $this->roleOf($actor),
                'action' => $action,
                'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
                'auditable_id' => $auditable?->getKey(),
                'context' => $context ?: null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function roleOf($actor): ?string
    {
        if ($actor === null || ! method_exists($actor, 'getRoleNames')) {
            return null;
        }

        return $actor->getRoleNames()->first();
    }
}
