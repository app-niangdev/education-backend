<?php

namespace App\Http\Requests;

use App\Enums\StatutAnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            // `en_cours` est exclue : voir StoreAnneeScolaireRequest.
            // Voir StoreAnneeScolaireRequest : les annees supprimees (soft
            // delete) ne doivent pas reserver leur nom.
            'nom'        => [
                'required', 'string', 'max:100',
                Rule::unique('annee_scolaires', 'nom')->ignore($id)->whereNull('deleted_at'),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after:date_debut'],
            'statut'     => ['required', Rule::enum(StatutAnneeScolaire::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'        => "Le nom de l'année scolaire est obligatoire.",
            'nom.unique'          => "Cette année scolaire existe déjà.",
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_fin.required'   => 'La date de fin est obligatoire.',
            'date_fin.after'      => 'La date de fin doit être postérieure à la date de début.',
            'statut.required'     => 'Le statut est obligatoire.',
            'statut.enum'         => 'Le statut sélectionné est invalide.',
        ];
    }
}
