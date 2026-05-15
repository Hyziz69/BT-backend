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
            'competencies' => $this->competencies ?? [],
            'leader'       => [
                'id'    => $this->leader->id,
                'name'  => $this->leader->first_name . ' ' . $this->leader->last_name,
                'email' => $this->leader->email,
            ],
            'members'      => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($m) => [
                    'id'        => $m->user->id,
                    'name'      => $m->user->first_name . ' ' . $m->user->last_name,
                    'email'     => $m->user->email,
                    'role'      => $m->role,
                    'joined_at' => $m->joined_at,
                ])
            ),
            'member_count' => $this->members->count(),
            'created_at'   => $this->created_at,
        ];
    }
}