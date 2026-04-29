<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Program extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['type', 'name', 'description', 'min_team_size', 'max_team_size', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function calls()
    {
        return $this->hasMany(Call::class);
    }
}
