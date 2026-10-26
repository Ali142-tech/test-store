<?php

namespace App\Observers;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/** Records create / update / delete of business-critical records (prices, coupons, shipping, approvals, settings). */
class AuditObserver
{
    public function created(Model $m): void
    {
        AuditLogger::log(class_basename($m).'.created', $m, $m->getAttributes());
    }

    public function updated(Model $m): void
    {
        $ignored = ['updated_at', 'remember_token', 'password', 'email_verified_at'];
        $changes = collect($m->getChanges())->except($ignored)->map(fn ($new, $k) => ['old' => $m->getOriginal($k), 'new' => $new])->all();
        if ($changes) {
            AuditLogger::log(class_basename($m).'.updated', $m, $changes);
        }
    }

    public function deleted(Model $m): void
    {
        AuditLogger::log(class_basename($m).'.deleted', $m);
    }
}
