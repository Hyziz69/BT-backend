<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:300'],
            'due_date' => ['nullable', 'date'],
            'comment'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}