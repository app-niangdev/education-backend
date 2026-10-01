<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'nom'         => ['required', 'string', 'max:100'],
            'code'        => [
                'required', 'string', 'max:20',
                Rule::unique('matieres', 'code')
                    ->ignore($this->route('id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'  => 'Le nom de la matière est obligatoire.',
            'code.required' => 'Le code de la matière est obligatoire.',
            'code.unique'   => 'Ce code de matière est déjà utilisé.',
        ];
    }
}
