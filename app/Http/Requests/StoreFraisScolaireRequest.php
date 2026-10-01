<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFraisScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id'    => ['required', 'integer', 'exists:annee_scolaires,id'],
            'niveau_id'            => ['required', 'integer', 'exists:niveaux,id'],
            'montant_inscription'  => ['required', 'integer', 'min:0'],
            'montant_mensualite'   => ['required', 'integer', 'min:0'],
            'nombre_mensualites'   => ['required', 'integer', 'min:1', 'max:12'],
            'neuvieme_mois_inclus' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'annee_scolaire_id.required' => "L'année scolaire est obligatoire.",
            'annee_scolaire_id.exists'   => "L'année scolaire sélectionnée est invalide.",
            'niveau_id.required'         => 'Le niveau est obligatoire.',
            'niveau_id.exists'           => 'Le niveau sélectionné est invalide.',
            'montant_inscription.required' => "Le montant d'inscription est obligatoire.",
            'montant_inscription.min'      => "Le montant d'inscription ne peut pas être négatif.",
            'montant_mensualite.required'  => 'Le montant de la mensualité est obligatoire.',
            'montant_mensualite.min'       => 'Le montant de la mensualité ne peut pas être négatif.',
            'nombre_mensualites.required'  => 'Le nombre de mensualités est obligatoire.',
            'nombre_mensualites.min'       => 'Le nombre de mensualités doit être au moins 1.',
            'nombre_mensualites.max'       => 'Le nombre de mensualités ne peut pas dépasser 12.',
        ];
    }
}
