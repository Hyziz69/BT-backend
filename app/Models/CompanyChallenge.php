<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CompanyChallenge extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'call_id', 'company_id', 'product_owner_id',
        'title', 'technical_spec', 'budget', 'status',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function productOwner()
    {
        return $this->belongsTo(User::class, 'product_owner_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'challenge_id');
    }
}
