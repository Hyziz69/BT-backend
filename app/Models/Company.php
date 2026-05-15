<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'ico',
        'sector',
        'description',
        'website',
        'logo_url',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function challenges()
    {
        return $this->hasMany(CompanyChallenge::class);
    }
}