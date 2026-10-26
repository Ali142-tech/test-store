<?php

namespace App\Console\Commands;

use App\Mail\CartReminderMail;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Abandoned-cart reminders: ONLY for signed-in customers who opted in to marketing, once per cart. */
class SendCartReminders extends Command
{
    protected $signature = 'carts:remind {--hours=24}';

    protected $description = 'Email consenting customers about carts idle for N hours (once per cart)';

    public function handle(): int
    {
        $n = 0;
        Cart::query()->whereNotNull('user_id')->whereNull('reminded_at')
            ->where('last_activity_at', '<', now()->subHours((int) $this->option('hours')))
            ->where('last_activity_at', '>', now()->subDays(7))
            ->whereHas('items')
            ->whereHas('user', fn ($q) => $q->where('marketing_consent', true)->where('is_active', true))
            ->with('user')->each(function (Cart $cart) use (&$n) {
                Mail::to($cart->user->email)->send(new CartReminderMail($cart->user));
                $cart->forceFill(['reminded_at' => now()])->save();
                $n++;
            });
        $this->info("Sent {$n} reminder(s).");

        return self::SUCCESS;
    }
}
