<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $adminUrl;

    public function __construct(public User $user)
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        $this->adminUrl = "{$frontendUrl}/admin";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New account approval request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-approval-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}