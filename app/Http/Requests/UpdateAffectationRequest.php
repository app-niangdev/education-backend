<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAffectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'enseignant_id'     => ['required', 'integer', 'exists:enseignants,id'],
            'classe_matiere_id' => ['required', 'integer', 'exists:classe_matiere,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'enseignant_id.required'     => "L'enseignant est obligatoire.",
            'enseignant_id.exists'       => "L'enseignant sélectionné est invalide.",
            'classe_matiere_id.required' => 'La matière de la classe est obligatoire.',
            'classe_matiere_id.exists'   => 'La matière de classe sélectionnée est invalide.',
        ];
    }
}
