<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'motivation_letter' => ['nullable', 'string', 'max:5000'],
            'solution_proposal' => ['nullable', 'string', 'max:10000'],
        ];
    }
}