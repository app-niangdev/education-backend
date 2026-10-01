<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Publication et dépublication en lot d'une classe sur une période.
 * Publier fige les bulletins ; dépublier casse ce gel et permet de les
 * recalculer. Les deux gestes sont réservés à l'admin et au manager.
 */
class PublierBulletinsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'classe_id'  => ['required', 'integer', 'exists:classes,id'],
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'classe_id.required'  => 'La classe est obligatoire.',
            'classe_id.exists'    => 'La classe sélectionnée est invalide.',
            'periode_id.required' => 'La période est obligatoire.',
            'periode_id.exists'   => 'La période sélectionnée est invalide.',
        ];
    }
}
