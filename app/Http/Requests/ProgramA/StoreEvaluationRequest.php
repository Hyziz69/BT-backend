<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'criterion' => ['required', 'string', 'max:200'],
            'score'     => ['required', 'numeric', 'min:0', 'max:100'],
            'comment'   => ['nullable', 'string', 'max:2000'],
        ];
    }
}