<?php

namespace App\Http\Requests;

use App\Enums\CycleNiveauEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nom'   => ['required', 'string', 'max:100'],
            // Idem création : les niveaux supprimés sont hors du périmètre
            // d'unicité, seul le niveau en cours d'édition est aussi ignoré.
            'code'  => [
                'required',
                'string',
                'max:20',
                Rule::unique('niveaux', 'code')->ignore($id)->whereNull('deleted_at'),
            ],
            'cycle' => ['required', Rule::enum(CycleNiveauEnum::class)],

            // Idem création : le barème de l'année en cours se modifie avec le
            // niveau, et se crée ici s'il n'existait pas encore (niveau saisi
            // avant l'ouverture de l'année scolaire).
            'montant_inscription'  => ['nullable', 'required_with:montant_mensualite', 'integer', 'min:0'],
            'montant_mensualite'   => ['nullable', 'required_with:montant_inscription', 'integer', 'min:0'],
            'nombre_mensualites'   => ['nullable', 'integer', 'min:1', 'max:12'],
            'neuvieme_mois_inclus' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'   => 'Le nom du niveau est obligatoire.',
            'code.required'  => 'Le code du niveau est obligatoire.',
            'code.unique'    => 'Ce code est déjà utilisé par un autre niveau.',
            'cycle.required' => 'Le cycle est obligatoire.',
            'cycle.enum'     => 'Le cycle sélectionné est invalide.',

            'montant_inscription.required_with' => "Les frais d'inscription sont obligatoires dès qu'une mensualité est saisie.",
            'montant_inscription.integer'       => "Les frais d'inscription doivent être un montant entier.",
            'montant_inscription.min'           => "Les frais d'inscription doivent être positifs.",
            'montant_mensualite.required_with'  => "La mensualité est obligatoire dès que des frais d'inscription sont saisis.",
            'montant_mensualite.integer'        => 'La mensualité doit être un montant entier.',
            'montant_mensualite.min'            => 'La mensualité doit être positive.',
            'nombre_mensualites.integer'        => 'Le nombre de mensualités doit être un entier.',
            'nombre_mensualites.min'            => 'Le nombre de mensualités doit être compris entre 1 et 12.',
            'nombre_mensualites.max'            => 'Le nombre de mensualités doit être compris entre 1 et 12.',
        ];
    }
}
