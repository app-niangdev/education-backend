<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'username'   => ['nullable', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone_one'  => ['sometimes', 'required', 'string', 'max:20'],
            'phone_two'  => ['nullable', 'string', 'max:20'],
            'address'    => ['nullable', 'string', 'max:255'],
            'password'   => ['nullable', 'string', 'min:6'],
            'role_id'    => ['sometimes', 'required', 'exists:roles,id'],
            'status'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required'  => 'Le nom est obligatoire.',
            'email.email'         => "L'adresse email n'est pas valide.",
            'phone_one.required'  => 'Le téléphone principal est obligatoire.',
            'password.min'        => 'Le mot de passe doit contenir au moins 6 caractères.',
            'role_id.exists'      => 'Le rôle sélectionné est invalide.',
            'status.boolean'      => 'Le statut doit être vrai ou faux.',
        ];
    }
}
