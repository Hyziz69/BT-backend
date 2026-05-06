<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorshipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'mentor'        => $this->whenLoaded('mentor', fn () => [
                'id'    => $this->mentor->id,
                'name'  => $this->mentor->first_name . ' ' . $this->mentor->last_name,
                'email' => $this->mentor->email,
            ]),
            'notes'         => $this->notes,
            'started_at'    => $this->started_at,
            'ended_at'      => $this->ended_at,
            'is_active'     => is_null($this->ended_at),
            'consultations' => $this->whenLoaded('consultations', fn () =>
                ConsultationResource::collection($this->consultations)
            ),
        ];
    }
}