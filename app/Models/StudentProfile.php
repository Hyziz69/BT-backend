<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'study_program', 'study_year', 'skills',
        'cv_url', 'academic_declaration', 'academic_notes',
    ];

    protected $casts = [
        'skills'               => 'array',
        'academic_declaration' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}