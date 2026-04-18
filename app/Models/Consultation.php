<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Consultation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['mentorship_id', 'scheduled_at', 'notes', 'feedback'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function mentorship() { return $this->belongsTo(Mentorship::class); }
}