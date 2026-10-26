<?php

namespace App\Services\Wholesale;

use App\Mail\QuoteMail;
use App\Models\Quote;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class QuoteService
{
    /** Publish (or revise) a quote to the customer. Every item must carry a quoted price. */
    public function send(Quote $quote, User $by): void
    {
        abort_unless($by->can('manage quotes'), 403);
        if (! in_array($quote->status, ['requested', 'quoted', 'revised', 'accepted'], true)) {
            throw new InvalidArgumentException("A {$quote->status} quote cannot be sent.");
        }
        $quote->load('items');
        if ($quote->items->contains(fn ($i) => $i->quoted_price === null)) {
            throw new InvalidArgumentException('Enter a quoted price for every item first.');
        }
        if (! $quote->expires_at || $quote->expires_at->isPast()) {
            throw new InvalidArgumentException('Set an expiry date in the future.');
        }

        DB::transaction(function () use ($quote, $by) {
            $revised = in_array($quote->status, ['quoted', 'accepted'], true);
            $total = $quote->items->reduce(fn ($c, $i) => bcadd($c, bcmul((string) $i->quoted_price, (string) $i->quantity, 2), 2), '0.00');

            $quote->update([
                'status' => 'quoted', 'quoted_total' => $total,
                'revision' => $revised ? $quote->revision + 1 : $quote->revision,
            ]);
            $quote->events()->create([
                'user_id' => $by->id, 'event' => $revised ? 'revised' : 'quoted',
                'snapshot' => $quote->items->map(fn ($i) => ['sku' => $i->variant->sku, 'qty' => $i->quantity, 'price' => (string) $i->quoted_price])->all(),
                'note' => $quote->admin_notes,
            ]);
        });

        SafeMail::run(fn () => Mail::to($quote->user->email)->send(new QuoteMail($quote->fresh())));
    }

    public function reject(Quote $quote, User $by, ?string $note): void
    {
        abort_unless($by->can('manage quotes'), 403);
        $quote->update(['status' => 'rejected', 'admin_notes' => $note ?? $quote->admin_notes]);
        $quote->events()->create(['user_id' => $by->id, 'event' => 'rejected', 'note' => $note]);
    }
}
