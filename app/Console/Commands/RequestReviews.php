<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Asks consenting customers to review products ~7 days after delivery (once per order). */
class RequestReviews extends Command
{
    protected $signature = 'reviews:request {--days=7}';

    protected $description = 'Email review requests for delivered orders (consenting registered customers only)';

    public function handle(): int
    {
        if (! \App\Support\SafeMail::enabled()) {
            $this->warn('Email is switched off (STORE_MAIL_ENABLED=false): nothing sent.');

            return self::SUCCESS;
        }

        $n = 0;
        Order::where('status', 'delivered')->whereNull('review_requested_at')->whereNotNull('user_id')
            ->whereHas('histories', fn ($q) => $q->where('to_status', 'delivered')->where('created_at', '<', now()->subDays((int) $this->option('days'))))
            ->whereHas('user', fn ($q) => $q->where('marketing_consent', true))
            ->with('user')->each(function (Order $order) use (&$n) {
                $url = route('account.orders.show', $order->reference);
                Mail::raw("Hi {$order->user->name},\n\nHow was your order {$order->reference}? Leave a review here: {$url}\n\nYou are receiving this because you opted in to marketing emails; turn them off in your profile.", fn ($m) => $m->to($order->user->email)->subject('How did we do?'));
                $order->forceFill(['review_requested_at' => now()])->save();
                $n++;
            });
        $this->info("Sent {$n} review request(s).");

        return self::SUCCESS;
    }
}
