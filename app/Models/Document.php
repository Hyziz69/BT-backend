<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Document extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id', 'uploaded_by', 'doc_type', 'classification',
        'filename', 'file_path', 'mime_type', 'file_size', 'version'
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}