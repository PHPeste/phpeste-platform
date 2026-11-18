<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class EditionMail extends Mailable
{
    public function __construct(public readonly RenderedEmail $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->email->fromName),
            subject: $this->email->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'notifications::edition-email-text',
            with: ['body' => $this->email->text],
            htmlString: $this->email->html,
        );
    }

    public function headers(): Headers
    {
        if ($this->email->unsubscribeUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->email->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
