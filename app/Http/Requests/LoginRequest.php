<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required_without_all:username,phone', 'nullable', 'email'],
            'username' => ['required_without_all:email,phone', 'nullable', 'string'],
            'phone'    => ['required_without_all:email,username', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without_all'    => 'Fournissez un e-mail, un nom d\'utilisateur ou un téléphone.',
            'username.required_without_all' => 'Fournissez un e-mail, un nom d\'utilisateur ou un téléphone.',
            'phone.required_without_all'    => 'Fournissez un e-mail, un nom d\'utilisateur ou un téléphone.',
            'password.required'             => 'Le mot de passe est obligatoire.',
        ];
    }

    /**
     * Ensure at least one login identifier is present.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('email') && ! $this->filled('username') && ! $this->filled('phone')) {
                $validator->errors()->add('login', 'Vous devez fournir un e-mail, un nom d\'utilisateur ou un numéro de téléphone.');
            }
        });
    }
}
