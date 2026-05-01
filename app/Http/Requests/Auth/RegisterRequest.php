<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:student,mentor,company_contact,editor,nti_admin,superadmin'],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }
}
