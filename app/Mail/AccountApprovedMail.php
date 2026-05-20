<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $loginUrl;

    public function __construct(public User $user)
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        $this->loginUrl = "{$frontendUrl}/login";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your NTI account was approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}