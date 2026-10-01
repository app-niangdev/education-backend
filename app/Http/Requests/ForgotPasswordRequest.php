<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Pas de regle `exists` : elle ferait repondre 422 sur une adresse
        // inconnue et transformerait ce formulaire en annuaire des comptes.
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email'    => 'Cette adresse e-mail n\'est pas valide.',
        ];
    }
}
