<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent extends Model
{
    use HasUuids; // No HasFactory needed usually for immutable logs

    public $timestamps = false; // We use created_at (useCurrent) in migration

    protected $fillable = ['actor_id', 'action', 'entity_type', 'entity_id', 'payload', 'ip_address', 'user_agent'];

    protected $casts = ['payload' => 'array'];

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}