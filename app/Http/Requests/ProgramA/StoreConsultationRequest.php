<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:3000'],
            'feedback'     => ['nullable', 'string', 'max:3000'],
        ];
    }
}