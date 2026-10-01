<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La génération porte sur une classe entière : le rang d'un élève n'a de sens
 * que rapporté à ses camarades. Réservée à l'admin et au manager, car elle
 * engage l'établissement.
 */
class GenererBulletinsRequest extends FormRequest
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
