<?php

namespace App\Http\Requests;

use App\Enums\StatutPresenceEnum;
use App\Enums\StatutSeanceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * L'appel d'un créneau à une date.
 *
 * Le contrôle « ce créneau est bien le mien » et la fenêtre de saisie de
 * l'enseignant vivent dans le service : ce sont des règles métier, pas de la
 * validation de forme.
 */
class EnregistrerAppelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role?->name,
            ['admin', 'manager', 'supervisor', 'teacher'],
            true,
        );
    }

    public function rules(): array
    {
        return [
            'emploi_du_temps_id' => ['required', 'integer', 'exists:emploi_du_temps,id'],
            // Faire l'appel d'un cours qui n'a pas encore eu lieu n'a pas de sens.
            'date_seance'        => ['required', 'date', 'before_or_equal:today'],
            'statut_seance'      => ['sometimes', new Enum(StatutSeanceEnum::class)],
            'commentaire'        => ['nullable', 'string', 'max:500'],

            // Seules les anomalies remontent : un élève présent n'a pas de ligne.
            'lignes'                  => ['sometimes', 'array'],
            'lignes.*.eleve_id'       => ['required', 'integer', 'distinct', 'exists:eleves,id'],
            'lignes.*.statut'         => ['required', new Enum(StatutPresenceEnum::class)],
            'lignes.*.minutes_retard' => ['nullable', 'integer', 'min:0', 'max:240'],
            'lignes.*.motif'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'emploi_du_temps_id.required' => 'Le créneau est obligatoire.',
            'emploi_du_temps_id.exists'   => 'Le créneau sélectionné est invalide.',
            'date_seance.required'        => "La date de l'appel est obligatoire.",
            'date_seance.before_or_equal' => "On ne peut pas faire l'appel d'un cours à venir.",
            'lignes.*.eleve_id.distinct'  => 'Un même élève ne peut apparaître deux fois.',
            'lignes.*.eleve_id.exists'    => 'Un des élèves sélectionnés est invalide.',
            'lignes.*.statut.required'    => 'Le statut est obligatoire pour chaque anomalie.',
            'lignes.*.minutes_retard.max' => 'Un retard ne peut pas dépasser 240 minutes.',
            'lignes.*.motif.max'          => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
