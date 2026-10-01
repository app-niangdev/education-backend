<?php

namespace App\Http\Requests;

use App\Enums\TypePeriodeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'libelle'               => ['required', 'string', 'max:100'],
            'type'                  => ['required', Rule::enum(TypePeriodeEnum::class)],
            'ordre'                 => ['required', 'integer', 'min:1'],
            'date_debut'            => ['required', 'date'],
            'date_fin'              => ['required', 'date', 'after:date_debut'],
            'date_fin_saisie_notes' => ['required', 'date', 'after_or_equal:date_debut'],
            'annee_scolaire_id'     => ['required', 'exists:annee_scolaires,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.required'               => 'Le libellé de la période est obligatoire.',
            'type.required'                  => 'Le type de période est obligatoire.',
            'type.enum'                      => 'Le type sélectionné est invalide.',
            'ordre.required'                 => "L'ordre est obligatoire.",
            'ordre.min'                      => "L'ordre doit être au minimum 1.",
            'date_debut.required'            => 'La date de début est obligatoire.',
            'date_fin.required'              => 'La date de fin est obligatoire.',
            'date_fin.after'                 => 'La date de fin doit être postérieure à la date de début.',
            'date_fin_saisie_notes.required' => 'La date de fin de saisie des notes est obligatoire.',
            'date_fin_saisie_notes.after_or_equal' => 'La date de fin de saisie doit être égale ou postérieure à la date de début.',
            'annee_scolaire_id.required'     => "L'année scolaire est obligatoire.",
            'annee_scolaire_id.exists'       => "L'année scolaire sélectionnée est invalide.",
        ];
    }
}
