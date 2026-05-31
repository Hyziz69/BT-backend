<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StudentProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'study_program',
        'study_year',
        'skills',
        'cv_path',
        'cv_url',
        'academic_declaration',
        'academic_notes',
    ];

    protected $casts = [
        'skills' => 'array',
        'academic_declaration' => 'boolean',
    ];

    protected $appends = [
        'cv_download_url',
    ];

    public function getCvDownloadUrlAttribute(): ?string
    {
        if ($this->cv_path) {
            return asset(Storage::url($this->cv_path));
        }

        return $this->cv_url;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}