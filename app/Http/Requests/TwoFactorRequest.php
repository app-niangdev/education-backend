<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled'  => ['required', 'boolean'],
            // Reactiver ou couper son second facteur touche a la securite du
            // compte : on redemande le mot de passe, faute de quoi un poste
            // laisse ouvert suffirait a desarmer la protection.
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required'  => 'Précisez si la double authentification doit être activée.',
            'password.required' => 'Votre mot de passe est requis pour cette opération.',
        ];
    }
}
