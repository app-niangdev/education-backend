<?php

namespace App\Http\Requests;

use App\Enums\JourSemaineEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmploiDuTempsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'classe_id'      => ['required', 'integer', 'exists:classes,id'],
            'affectation_id' => ['required', 'integer', 'exists:affectations,id'],
            'jour'           => ['required', new Enum(JourSemaineEnum::class)],
            'heure_debut'    => ['required', 'date_format:H:i'],
            'heure_fin'      => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle'          => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'classe_id.required'      => 'La classe est obligatoire.',
            'classe_id.exists'        => 'La classe sélectionnée est invalide.',
            'affectation_id.required' => "L'affectation (matière + enseignant) est obligatoire.",
            'affectation_id.exists'   => "L'affectation sélectionnée est invalide.",
            'jour.required'           => 'Le jour est obligatoire.',
            'heure_debut.required'    => "L'heure de début est obligatoire.",
            'heure_debut.date_format' => "L'heure de début doit être au format HH:MM.",
            'heure_fin.required'      => "L'heure de fin est obligatoire.",
            'heure_fin.date_format'   => "L'heure de fin doit être au format HH:MM.",
            'heure_fin.after'         => "L'heure de fin doit être postérieure à l'heure de début.",
        ];
    }
}
