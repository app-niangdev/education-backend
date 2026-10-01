<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'nom'               => [
                'required', 'string', 'max:100',
                Rule::unique('classes', 'nom')
                    ->where('annee_scolaire_id', $this->input('annee_scolaire_id'))
                    ->whereNull('deleted_at'),
            ],
            'code'              => ['nullable', 'string', 'max:20'],
            'effectif_max'      => ['nullable', 'integer', 'min:1'],
            'niveau_id'         => ['required', 'integer', 'exists:niveaux,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'               => 'Le nom de la classe est obligatoire.',
            'nom.unique'                 => 'Une classe portant ce nom existe déjà pour cette année scolaire.',
            'effectif_max.min'           => "L'effectif maximum doit être d'au moins 1.",
            'niveau_id.required'         => 'Le niveau est obligatoire.',
            'niveau_id.exists'           => 'Le niveau sélectionné est invalide.',
            'annee_scolaire_id.required' => "L'année scolaire est obligatoire.",
            'annee_scolaire_id.exists'   => "L'année scolaire sélectionnée est invalide.",
        ];
    }
}
