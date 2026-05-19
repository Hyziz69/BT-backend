<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends Notification
{
    public function __construct(private string $url) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password — NTI Portal')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('You requested a password reset. Click the button below to set a new password.')
            ->action('Reset Password', $this->url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }
}
