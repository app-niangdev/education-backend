<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    /**
     * eleve_id, annee_scolaire_id, montant_inscription, numero_inscription et
     * type_inscription ne sont pas modifiables : ils sont derives cote serveur.
     */
    public function rules(): array
    {
        return [
            'classe_id'        => ['sometimes', 'integer', 'exists:classes,id'],
            'date_inscription' => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'classe_id.exists'      => 'La classe sélectionnée est invalide.',
            'date_inscription.date' => "La date d'inscription est invalide.",
        ];
    }
}
