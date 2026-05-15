<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Application extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'call_id', 'team_id', 'challenge_id', 'status',
        'motivation_letter', 'solution_proposal', 'score',
        'decision_notes', 'submitted_at', 'decided_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    public function challenge() // For Program B
    {
        return $this->belongsTo(CompanyChallenge::class, 'challenge_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function mentorships()
    {
        return $this->hasMany(Mentorship::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }
}