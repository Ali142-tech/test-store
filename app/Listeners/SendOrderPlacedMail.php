<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderMail;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Mail;

class SendOrderPlacedMail
{
    public function handle(OrderPlaced $event): void
    {
        SafeMail::run(fn () => Mail::to($event->order->email)->send(new OrderMail($event->order, 'placed')));
    }
}
