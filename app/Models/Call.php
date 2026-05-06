<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Call extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'program_id', 'created_by', 'title', 'description',
        'status', 'evaluation_criteria', 'opens_at', 'closes_at',
    ];

    protected $casts = [
        'evaluation_criteria' => 'array',
        'opens_at'            => 'datetime',
        'closes_at'           => 'datetime',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function challenges()
    {
        return $this->hasMany(CompanyChallenge::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}