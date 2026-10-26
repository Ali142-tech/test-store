<?php

namespace App\Services\Wholesale;

use App\Mail\WholesaleDecisionMail;
use App\Models\User;
use App\Models\WholesaleApplication;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class WholesaleApprovalService
{
    /** The ONLY code path that grants wholesale access: sets the application status and the account type together. */
    public function approve(WholesaleApplication $app, User $reviewer, ?string $notes = null): void
    {
        $this->review($app, $reviewer, 'approved', $notes, 'wholesale');
    }

    public function reject(WholesaleApplication $app, User $reviewer, ?string $notes = null): void
    {
        $this->review($app, $reviewer, 'rejected', $notes, 'retail');
    }

    private function review(WholesaleApplication $app, User $reviewer, string $status, ?string $notes, string $type): void
    {
        if (! $reviewer->can('manage wholesale')) {
            throw new InvalidArgumentException('You are not allowed to review wholesale applications.');
        }

        DB::transaction(function () use ($app, $reviewer, $status, $notes, $type) {
            $app->forceFill(['status' => $status, 'admin_notes' => $notes, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();
            $app->user->forceFill(['customer_type' => $type])->save();
            AuditLogger::log("Wholesale.{$status}", $app, ['user_id' => $app->user_id, 'notes' => $notes]);
        });

        Mail::to($app->user->email)->send(new WholesaleDecisionMail($app->fresh()));
    }
}
