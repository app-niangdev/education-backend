<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Remontee d'un fil a la direction.
 *
 * Le motif est obligatoire : la direction recoit un dossier qu'elle n'a pas
 * suivi, elle doit savoir sur quoi le service de premier niveau a bute. Sans
 * cette contrainte, l'escalade deviendrait un moyen de se debarrasser d'une
 * demande sans l'instruire.
 *
 * Le tuteur ne passe jamais par ici : le controle de role est fait dans le
 * service, qui refuse l'escalade a quiconque n'est pas agent.
 */
class EscaladerConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()->role?->name,
            ['admin', 'manager', 'supervisor', 'treasurer'],
            true,
        );
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => "Le motif de l'escalade est obligatoire : la direction doit savoir pourquoi le dossier lui est transmis.",
            'motif.min'      => 'Le motif doit être explicite (10 caractères au minimum).',
            'motif.max'      => 'Le motif ne peut pas dépasser 1000 caractères.',
        ];
    }
}
