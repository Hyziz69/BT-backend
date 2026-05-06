<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'doc_type'       => $this->doc_type,
            'classification' => $this->classification,
            'filename'       => $this->filename,
            'mime_type'      => $this->mime_type,
            'file_size'      => $this->file_size,
            'version'        => $this->version,
            'uploaded_by'    => [
                'id'   => $this->uploadedBy->id,
                'name' => $this->uploadedBy->first_name . ' ' . $this->uploadedBy->last_name,
            ],
            'created_at'   => $this->created_at,
            'download_url' => route('program-a.applications.documents.download', [
                'application' => $this->application_id,
                'document'    => $this->id,
            ]),
        ];
    }
}