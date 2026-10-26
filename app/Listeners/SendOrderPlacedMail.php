<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderMail;
use Illuminate\Support\Facades\Mail;

class SendOrderPlacedMail
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->email)->send(new OrderMail($event->order, 'placed'));
    }
}
