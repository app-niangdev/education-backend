<?php

namespace App\Http\Requests;

use App\Enums\StatutAnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    /**
     * `en_cours` est acceptee ici, mais uniquement pour amorcer : tant qu'aucune
     * annee n'est active, l'application n'a pas d'annee de reference et une
     * douzaine de repositories filtrent dans le vide. La premiere annee doit
     * donc pouvoir s'activer a la creation.
     *
     * Des qu'une annee est active, la bascule redevient l'affaire de la cloture
     * d'annee (fermer avant d'ouvrir) : le service rejette alors la demande,
     * et l'index unique en base refuserait de toute facon la seconde ligne.
     */
    public function rules(): array
    {
        return [
            // Le modele est en SoftDeletes : sans `whereNull('deleted_at')`,
            // une annee supprimee — invisible dans l'interface — continuerait
            // d'interdire la reutilisation de son nom.
            'nom'        => [
                'required', 'string', 'max:100',
                Rule::unique('annee_scolaires', 'nom')->whereNull('deleted_at'),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after:date_debut'],
            'statut'     => ['required', Rule::enum(StatutAnneeScolaire::class)],
            'en_cours'   => ['sometimes', 'boolean'],
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
