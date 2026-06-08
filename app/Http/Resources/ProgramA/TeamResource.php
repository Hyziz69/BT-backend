<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'invite_code'  => $this->invite_code,
            'competencies' => $this->competencies ?? [],
            'leader'       => [
                'id'    => $this->leader->id,
                'name'  => $this->leader->first_name . ' ' . $this->leader->last_name,
                'email' => $this->leader->email,
            ],
            'members' => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($m) => [
                    'id'        => $m->id,
                    'name'      => $m->first_name . ' ' . $m->last_name,
                    'email'     => $m->email,
                    'role'      => $m->pivot->role,
                    'joined_at' => $m->pivot->joined_at,
                ])
            ),
            'member_count' => $this->members->count(),
            'created_at'   => $this->created_at,
        ];
    }
}