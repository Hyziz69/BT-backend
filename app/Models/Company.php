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

    /**
     * The user who created the company and holds full control.
     */
    public function owner()
    {
        return $this->hasOne(User::class)->where('company_role', 'owner');
    }

    /**
     * All users that are part of this company's management (owner + managers + members).
     */
    public function members()
    {
        return $this->hasMany(User::class)->whereNotNull('company_role');
    }

    public function invitations()
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function challenges()
    {
        return $this->hasMany(CompanyChallenge::class);
    }
}