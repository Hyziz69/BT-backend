<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'criterion' => $this->criterion,
            'score'     => $this->score,
            'comment'   => $this->comment,
            'evaluator' => $this->whenLoaded('evaluator', fn () => [
                'id'   => $this->evaluator->id,
                'name' => $this->evaluator->first_name . ' ' . $this->evaluator->last_name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}