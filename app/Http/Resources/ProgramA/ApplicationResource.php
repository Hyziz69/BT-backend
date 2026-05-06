<?php
namespace App\Http\Resources\ProgramA;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'status'            => $this->status,
            'score'             => $this->score,
            'motivation_letter' => $this->motivation_letter,
            'solution_proposal' => $this->solution_proposal,
            'decision_notes'    => $this->when(
                in_array(auth()->user()?->account_type, ['nti_admin', 'superadmin', 'evaluator']),
                $this->decision_notes
            ),
            'submitted_at' => $this->submitted_at,
            'decided_at'   => $this->decided_at,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'team'         => $this->whenLoaded('team', fn () => new TeamResource($this->team)),
            'call'         => $this->whenLoaded('call', fn () => [
                'id'        => $this->call->id,
                'title'     => $this->call->title,
                'status'    => $this->call->status,
                'closes_at' => $this->call->closes_at,
                'program'   => [
                    'id'   => $this->call->program->id,
                    'type' => $this->call->program->type,
                    'name' => $this->call->program->name,
                ],
            ]),
            'documents'   => $this->whenLoaded('documents', fn () => DocumentResource::collection($this->documents)),
            'evaluations' => $this->whenLoaded('evaluations', fn () => EvaluationResource::collection($this->evaluations)),
            'mentorships' => $this->whenLoaded('mentorships', fn () => MentorshipResource::collection($this->mentorships)),
            'milestones'  => $this->whenLoaded('milestones', fn () => MilestoneResource::collection($this->milestones)),
        ];
    }
}