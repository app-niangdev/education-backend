<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClasseMatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'coefficient'    => ['required', 'integer', 'min:1', 'max:20'],
            'volume_horaire' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'coefficient.required' => 'Le coefficient est obligatoire.',
            'coefficient.min'      => 'Le coefficient doit être au minimum de 1.',
            'coefficient.max'      => 'Le coefficient ne peut pas dépasser 20.',
        ];
    }
}
