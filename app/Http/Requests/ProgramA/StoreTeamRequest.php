<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:200'],
            'competencies'   => ['sometimes', 'array'],
            'competencies.*' => ['string', 'max:100'],
        ];
    }
}