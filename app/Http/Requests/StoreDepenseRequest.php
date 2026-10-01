<?php

namespace App\Http\Requests;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager', 'treasurer'], true);
    }

    public function rules(): array
    {
        return [
            // Facultatif : le service retombe sur l'annee scolaire en cours.
            'annee_scolaire_id' => ['sometimes', 'nullable', 'integer', 'exists:annee_scolaires,id'],
            'libelle'           => ['required', 'string', 'max:255'],
            'categorie'         => ['required', Rule::enum(CategorieDepenseEnum::class)],
            'montant'           => ['required', 'integer', 'min:1'],
            'mode_paiement'     => ['required', Rule::enum(ModePaiementEnum::class)],
            'beneficiaire'      => ['nullable', 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:100'],
            // Une depense ne s'enregistre pas dans le futur.
            'date_depense'      => ['required', 'date', 'before_or_equal:today'],
            'description'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.required'            => 'Le libellé de la dépense est obligatoire.',
            'categorie.required'          => 'La catégorie est obligatoire.',
            'categorie.enum'              => 'La catégorie sélectionnée est invalide.',
            'montant.required'            => 'Le montant est obligatoire.',
            'montant.min'                 => 'Le montant doit être supérieur à zéro.',
            'mode_paiement.required'      => 'Le mode de paiement est obligatoire.',
            'mode_paiement.enum'          => 'Le mode de paiement sélectionné est invalide.',
            'date_depense.required'       => 'La date de la dépense est obligatoire.',
            'date_depense.before_or_equal' => 'La date de la dépense ne peut pas être dans le futur.',
            'annee_scolaire_id.exists'    => "L'année scolaire sélectionnée n'existe pas.",
        ];
    }
}
