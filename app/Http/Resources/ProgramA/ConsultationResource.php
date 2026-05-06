<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'scheduled_at' => $this->scheduled_at,
            'notes'        => $this->notes,
            'feedback'     => $this->feedback,
            'created_at'   => $this->created_at,
        ];
    }
}