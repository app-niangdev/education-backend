<?php

namespace App\Http\Requests;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager', 'treasurer'], true);
    }

    /** Modification partielle : chaque champ garde sa valeur s'il est absent. */
    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['sometimes', 'integer', 'exists:annee_scolaires,id'],
            'libelle'           => ['sometimes', 'string', 'max:255'],
            'categorie'         => ['sometimes', Rule::enum(CategorieDepenseEnum::class)],
            'montant'           => ['sometimes', 'integer', 'min:1'],
            'mode_paiement'     => ['sometimes', Rule::enum(ModePaiementEnum::class)],
            'beneficiaire'      => ['nullable', 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'date_depense'      => ['sometimes', 'date', 'before_or_equal:today'],
            'description'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'categorie.enum'               => 'La catégorie sélectionnée est invalide.',
            'montant.min'                  => 'Le montant doit être supérieur à zéro.',
            'mode_paiement.enum'           => 'Le mode de paiement sélectionné est invalide.',
            'date_depense.before_or_equal' => 'La date de la dépense ne peut pas être dans le futur.',
            'annee_scolaire_id.exists'     => "L'année scolaire sélectionnée n'existe pas.",
        ];
    }
}
