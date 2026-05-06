<?php
namespace App\Http\Requests\ProgramA;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'file'           => ['required', 'file', 'max:20480'],
            'doc_type'       => ['required', 'string', 'in:executive_summary,tech_architecture,roadmap,budget,risk_analysis,monetization,cv,attachment,other'],
            'classification' => ['sometimes', 'string', 'in:public,internal,confidential'],
        ];
    }
}