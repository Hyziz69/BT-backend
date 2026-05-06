<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Milestone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'title', 'status', 'due_date', 'comment'];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
