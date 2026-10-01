<?php

namespace App\Http\Requests;

use App\Enums\ServiceDestinataireEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ouverture d'un fil.
 *
 * La regle « la direction ne se saisit pas directement » n'est pas verifiee
 * ici mais dans le service : elle depend de qui parle, et le service est le
 * seul endroit ou cette decision est prise une fois pour toutes (l'API n'est
 * pas le seul appelant possible).
 */
class StoreConversationRequest extends FormRequest
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
            // Ignore quand c'est un tuteur qui ecrit : le service prend alors
            // l'id de son propre compte. Obligatoire cote agent, mais la
            // verification vit dans le service, qui sait qui parle.
            'tuteur_id' => ['sometimes', 'nullable', 'integer', 'exists:tuteurs,id'],

            'eleve_id'  => ['sometimes', 'nullable', 'integer', 'exists:eleves,id'],

            'service'   => ['required', Rule::enum(ServiceDestinataireEnum::class)],

            'sujet'     => ['required', 'string', 'max:255'],

            // Le premier message part avec l'ouverture : un fil vide ne dit
            // pas au service de quoi il s'agit.
            'corps'     => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'service.required'  => 'Le service destinataire est obligatoire.',
            'service.enum'      => 'Le service sélectionné est invalide.',
            'sujet.required'    => "L'objet de la demande est obligatoire.",
            'sujet.max'         => "L'objet ne peut pas dépasser 255 caractères.",
            'corps.required'    => 'Le message ne peut pas être vide.',
            'corps.max'         => 'Le message ne peut pas dépasser 5000 caractères.',
            'tuteur_id.exists'  => "Le tuteur sélectionné n'existe pas.",
            'eleve_id.exists'   => "L'élève sélectionné n'existe pas.",
        ];
    }
}
