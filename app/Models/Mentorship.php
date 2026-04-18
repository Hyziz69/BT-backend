<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Mentorship extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'mentor_id', 'notes', 'started_at', 'ended_at'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function application() { return $this->belongsTo(Application::class); }
    public function mentor() { return $this->belongsTo(User::class, 'mentor_id'); }
    public function consultations() { return $this->hasMany(Consultation::class); }
}