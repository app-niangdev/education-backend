<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Saisie en lot des notes d'une évaluation. Le contrôle « valeur ≤ barème »
 * et « élève bien inscrit dans la classe » est fait dans le service, car il
 * dépend de l'évaluation ciblée.
 */
class SaveNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager', 'teacher']);
    }

    public function rules(): array
    {
        return [
            'notes'                => ['required', 'array', 'min:1'],
            'notes.*.eleve_id'     => ['required', 'integer', 'distinct', 'exists:eleves,id'],
            'notes.*.valeur'       => ['nullable', 'numeric', 'min:0'],
            'notes.*.absent'       => ['boolean'],
            'notes.*.appreciation' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required'            => 'Aucune note à enregistrer.',
            'notes.*.eleve_id.required' => "L'élève est obligatoire pour chaque note.",
            'notes.*.eleve_id.distinct' => 'Un même élève apparaît plusieurs fois.',
            'notes.*.valeur.min'        => 'Une note ne peut pas être négative.',
        ];
    }
}
