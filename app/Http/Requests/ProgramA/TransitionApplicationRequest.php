<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'status'         => ['required', 'string', 'in:submitted,formally_verified,in_evaluation,pending_supplement,approved,rejected,onboarding,active,paused,completed,archived'],
            'decision_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}