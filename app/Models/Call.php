<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Call extends Model
{
    /** @use HasFactory<\Database\Factories\CallFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['program_id', 'created_by', 'title', 'description', 'status', 'evaluation_criteria', 'opens_at', 'closes_at'];

    public function program()
    {
        return $this->belongsTo(Program::class);
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
