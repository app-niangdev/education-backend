<?php

namespace App\Http\Requests;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use App\Enums\StatutDepenseEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les criteres de recherche du module Depenses, valides avant d'atteindre la
 * base : une date mal formee doit produire un 422 explicite, pas une requete
 * SQL qui echoue ou un filtre silencieusement ignore.
 *
 * Les trois filtres de date sont exclusifs cote traitement (jour precis, puis
 * intervalle, puis mois) — voir DepenseRepository::filtrerParDate().
 */
class FiltreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager', 'treasurer'], true);
    }

    public function rules(): array
    {
        return [
            'search'            => ['nullable', 'string', 'max:255'],
            'categorie'         => ['nullable', Rule::enum(CategorieDepenseEnum::class)],
            'mode_paiement'     => ['nullable', Rule::enum(ModePaiementEnum::class)],
            // « Qu'est-ce qui attend ma decision » : le filtre de travail du
            // manager, et celui du tresorier qui suit ses saisies.
            'statut'            => ['nullable', Rule::enum(StatutDepenseEnum::class)],
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annee_scolaires,id'],
            'toutes_annees'     => ['nullable', 'boolean'],

            // Filtre 1 : une date precise.
            'date'      => ['nullable', 'date'],
            // Filtre 2 : un intervalle, bornes incluses.
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            // Filtre 3 : un mois de l'annee scolaire (les deux ou aucun).
            'mois'  => ['nullable', 'integer', 'between:1,12', 'required_with:annee'],
            'annee' => ['nullable', 'integer', 'digits:4', 'required_with:mois'],

            'per_page' => ['nullable', 'integer', 'between:1,200'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date'               => "La date de recherche n'est pas une date valide.",
            'date_from.date'          => "La date de début n'est pas une date valide.",
            'date_to.date'            => "La date de fin n'est pas une date valide.",
            'date_to.after_or_equal'  => 'La date de fin doit être postérieure ou égale à la date de début.',
            'mois.between'            => 'Le mois doit être compris entre 1 et 12.',
            'mois.required_with'      => "Le mois est obligatoire lorsqu'une année est précisée.",
            'annee.required_with'     => "L'année est obligatoire lorsqu'un mois est précisé.",
            'annee.digits'            => "L'année doit comporter 4 chiffres.",
        ];
    }

    /** Les criteres reellement exploitables, vides ecartes. */
    public function filtres(): array
    {
        return array_filter(
            $this->only([
                'search', 'categorie', 'mode_paiement', 'statut', 'annee_scolaire_id',
                'toutes_annees', 'date', 'date_from', 'date_to', 'mois', 'annee',
            ]),
            fn ($valeur) => $valeur !== null && $valeur !== '',
        );
    }
}
