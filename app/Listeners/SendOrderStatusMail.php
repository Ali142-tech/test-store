<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderMail;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Mail;

class SendOrderStatusMail
{
    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->order->status, ['confirmed', 'shipped', 'delivered', 'cancelled'], true)) {
            SafeMail::run(fn () => Mail::to($event->order->email)->send(new OrderMail($event->order, 'status')));
        }
    }
}
