<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Transactional order email (confirmation, status updates). Never used for marketing. */
class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $kind = 'placed') {}

    public function envelope(): Envelope
    {
        $subject = match ($this->kind) {
            'placed' => "Order {$this->order->reference} confirmed",
            default => "Order {$this->order->reference}: ".$this->order->status,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order', with: ['order' => $this->order->load('items'), 'kind' => $this->kind]);
    }
}
