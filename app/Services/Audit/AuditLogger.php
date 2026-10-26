<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** Attributes that must never be written to the audit trail. */
    private const REDACT = ['password', 'remember_token'];

    public static function log(string $action, ?Model $subject = null, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => array_diff_key($changes, array_flip(self::REDACT)) ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
