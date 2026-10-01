<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClasseMatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'classe_id'      => ['required', 'integer', 'exists:classes,id'],
            'matiere_id'     => ['required', 'integer', 'exists:matieres,id'],
            'coefficient'    => ['required', 'integer', 'min:1', 'max:20'],
            'volume_horaire' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'classe_id.required'   => 'La classe est obligatoire.',
            'classe_id.exists'     => 'La classe sélectionnée est invalide.',
            'matiere_id.required'  => 'La matière est obligatoire.',
            'matiere_id.exists'    => 'La matière sélectionnée est invalide.',
            'coefficient.required' => 'Le coefficient est obligatoire.',
            'coefficient.min'      => 'Le coefficient doit être au minimum de 1.',
            'coefficient.max'      => 'Le coefficient ne peut pas dépasser 20.',
        ];
    }
}
