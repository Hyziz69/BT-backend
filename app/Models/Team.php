<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['leader_id', 'name', 'competencies', 'invite_code'];

    protected $casts = [
        'competencies' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            do {
                $code = strtoupper(Str::random(8));
            } while (Team::where('invite_code', $code)->exists());
            $team->invite_code = $code;
        });
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('role', 'joined_at');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'team_members')
                    ->withPivot('role', 'joined_at');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
