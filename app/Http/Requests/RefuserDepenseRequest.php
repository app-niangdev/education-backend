<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le refus d'une depense.
 *
 * Le motif est obligatoire : c'est la seule chose qui permette au tresorier de
 * comprendre ce qui a ete ecarte et, le cas echeant, de corriger sa saisie. Un
 * refus sans explication laisserait la depense bloquee sans recours.
 */
class RefuserDepenseRequest extends FormRequest
{
    /** Valider ou refuser engage l'etablissement : manager et admin seuls. */
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'motif_refus' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif_refus.required' => 'Le motif du refus est obligatoire.',
            'motif_refus.min'      => 'Le motif doit être un peu plus explicite (5 caractères au minimum).',
            'motif_refus.max'      => 'Le motif ne peut pas dépasser 2000 caractères.',
        ];
    }
}
