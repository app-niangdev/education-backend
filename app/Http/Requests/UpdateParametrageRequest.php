<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise a jour du parametrage applicatif, depuis la page Etablissement.
 *
 * Ouverte au manager, a une reserve pres : `statut` et `en_maintenance`
 * coupent l'acces a la plateforme entiere, ils restent la main de l'admin.
 * Le formulaire les masque deja pour le manager, mais le client n'est pas la
 * garde : les champs sont ecartes ici avant validation.
 */
class UpdateParametrageRequest extends FormRequest
{
    /** Champs qui pilotent la disponibilite de la plateforme. */
    private const CHAMPS_ADMIN = ['statut', 'en_maintenance'];

    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager'], true);
    }

    /**
     * Un manager qui posterait `statut` ou `en_maintenance` les voit ignores,
     * pas rejetes : le reste de sa modification passe normalement.
     */
    protected function prepareForValidation(): void
    {
        if ($this->estAdmin()) {
            return;
        }

        $this->replace($this->except(self::CHAMPS_ADMIN));
    }

    public function rules(): array
    {
        $rules = [
            'telephone_transaction' => ['sometimes', 'nullable', 'string', 'max:20'],
            'code_couleur'          => ['sometimes', 'nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'bareme_defaut'         => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];

        if ($this->estAdmin()) {
            $rules['statut']         = ['sometimes', 'boolean'];
            $rules['en_maintenance'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    private function estAdmin(): bool
    {
        return $this->user()?->role?->name === 'admin';
    }

    public function messages(): array
    {
        return [
            'telephone_transaction.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'statut.boolean'            => 'Le statut doit être vrai ou faux.',
            'en_maintenance.boolean'    => 'La valeur maintenance doit être vrai ou faux.',
            'code_couleur.regex'        => 'Le code couleur doit être un code hexadécimal valide (ex: #FF5733).',
            'bareme_defaut.min'         => 'Le barème par défaut doit être au moins de 1.',
            'bareme_defaut.max'         => 'Le barème par défaut ne peut pas dépasser 100.',
        ];
    }
}
