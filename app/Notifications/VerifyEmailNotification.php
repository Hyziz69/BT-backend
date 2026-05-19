<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $id   = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        // Generate signed backend URL, then extract params for the frontend link
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $id, 'hash' => $hash]
        );

        parse_str(parse_url($backendUrl, PHP_URL_QUERY), $params);

        $frontendUrl = env('FRONTEND_URL') . '/verify-email?' . http_build_query([
            'id'        => $id,
            'hash'      => $hash,
            'expires'   => $params['expires'],
            'signature' => $params['signature'],
        ]);

        return (new MailMessage)
            ->subject('Verify Your Email Address — NTI Portal')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Thank you for registering. Please verify your email address to continue.')
            ->action('Verify Email Address', $frontendUrl)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not create an account, ignore this email.');
    }
}
