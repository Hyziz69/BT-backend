<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'type', 'subject', 'body', 'is_read', 'sent_at'];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create one unread notification for a user.
     */
    public static function notifyUser(string $userId, string $type, string $subject, string $body): void
    {
        static::create([
            'user_id' => $userId,
            'type'    => $type,
            'subject' => $subject,
            'body'    => $body,
            'is_read' => false,
            'sent_at' => now(),
        ]);
    }

    /**
     * Create the same notification for many users (e.g. all team members).
     */
    public static function notifyUsers(iterable $userIds, string $type, string $subject, string $body): void
    {
        foreach ($userIds as $userId) {
            if ($userId) {
                static::notifyUser($userId, $type, $subject, $body);
            }
        }
    }
}