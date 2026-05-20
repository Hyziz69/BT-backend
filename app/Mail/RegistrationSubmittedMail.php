<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->user->account_type) {
            'mentor' => 'Thanks for your interest in becoming an NTI mentor',
            'company_contact' => 'Thanks for your interest in joining NTI as a company',
            default => 'Thanks for your interest in joining NTI',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $view = match ($this->user->account_type) {
            'mentor' => 'emails.registration-submitted-mentor',
            'company_contact' => 'emails.registration-submitted-company',
            default => 'emails.registration-submitted-student',
        };

        return new Content(
            view: $view,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}