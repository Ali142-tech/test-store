<?php

namespace App\Mail;

use App\Models\WholesaleApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WholesaleDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WholesaleApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->application->status === 'approved' ? 'Your wholesale account is approved' : 'Update on your wholesale application');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.wholesale-decision');
    }
}
