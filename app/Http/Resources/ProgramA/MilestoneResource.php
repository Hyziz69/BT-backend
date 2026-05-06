<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'status'     => $this->status,
            'due_date'   => $this->due_date,
            'comment'    => $this->comment,
            'is_overdue' => $this->due_date && $this->due_date < now() && $this->status !== 'completed',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}