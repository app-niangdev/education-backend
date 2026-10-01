<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'username'   => ['nullable', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone_one'  => ['required', 'string', 'max:20'],
            'phone_two'  => ['nullable', 'string', 'max:20'],
            'address'    => ['nullable', 'string', 'max:255'],
            'role_id'    => ['required', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.max'      => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.required'  => 'Le nom est obligatoire.',
            'last_name.max'       => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.email'         => "L'adresse email n'est pas valide.",
            'email.max'           => "L'email ne peut pas dépasser 255 caractères.",
            'phone_one.required'  => 'Le téléphone principal est obligatoire.',
            'phone_one.max'       => 'Le téléphone ne peut pas dépasser 20 caractères.',
            'role_id.required'    => 'Le rôle est obligatoire.',
            'role_id.exists'      => 'Le rôle sélectionné est invalide.',
        ];
    }
}
