<?php

namespace App\Http\Requests;

use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les criteres de la liste des conversations.
 *
 * Le filtre `service` ne donne acces a rien : la portee est decidee par le
 * role dans ConversationRepository::paginatePourAgent(). Demander un service
 * qu'on ne traite pas renvoie une liste vide, jamais les fils d'autrui.
 */
class FiltreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()->role?->name,
            ['admin', 'manager', 'supervisor', 'treasurer', 'tuteur'],
            true,
        );
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'service'  => ['nullable', Rule::enum(ServiceDestinataireEnum::class)],
            'statut'   => ['nullable', Rule::enum(StatutConversationEnum::class)],
            'eleve_id' => ['nullable', 'integer', 'exists:eleves,id'],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            // Raccourci « a traiter » : tout sauf resolu et archive.
            'en_cours' => ['nullable', 'boolean'],

            'per_page' => ['nullable', 'integer', 'between:1,200'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'service.enum'    => 'Le service sélectionné est invalide.',
            'statut.enum'     => 'Le statut sélectionné est invalide.',
            'eleve_id.exists' => "L'élève sélectionné n'existe pas.",
        ];
    }

    /** Les criteres reellement exploitables, vides ecartes. */
    public function filtres(): array
    {
        return array_filter(
            $this->only(['search', 'service', 'statut', 'eleve_id', 'agent_id', 'en_cours']),
            fn ($valeur) => $valeur !== null && $valeur !== '',
        );
    }
}
