<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fin anticipee d'un contrat. Le motif est exige : une rupture sans raison
 * consignee laisse le dossier de l'employe inexploitable en cas de litige.
 */
class ResilierContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            // Le service refuse une date anterieure au debut du contrat.
            'date_resiliation'  => ['nullable', 'date'],
            'motif_resiliation' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_resiliation.date'      => "La date de résiliation n'est pas valide.",
            'motif_resiliation.required' => 'Le motif de la résiliation est obligatoire.',
            'motif_resiliation.min'      => 'Le motif doit être un peu plus explicite.',
        ];
    }
}
