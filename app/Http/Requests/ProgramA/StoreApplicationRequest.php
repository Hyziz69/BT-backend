<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'call_id'           => ['required', 'uuid', 'exists:calls,id'],
            'team_id'           => ['required', 'uuid', 'exists:teams,id'],
            'motivation_letter' => ['nullable', 'string', 'max:5000'],
            'solution_proposal' => ['nullable', 'string', 'max:10000'],
        ];
    }
}