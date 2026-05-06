<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class AssignMentorRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'mentor_id' => ['required', 'uuid', 'exists:users,id'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ];
    }
}