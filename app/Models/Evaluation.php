<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Evaluation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'evaluator_id', 'criterion', 'score', 'comment'];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}