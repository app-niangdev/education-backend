<?php

namespace App\Http\Requests;

use App\Enums\StatutPresenceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Correction d'une anomalie par la vie scolaire : un élève noté absent était
 * en réalité en retard, ou l'inverse.
 */
class UpdatePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager', 'supervisor'], true);
    }

    public function rules(): array
    {
        return [
            'statut'         => ['sometimes', new Enum(StatutPresenceEnum::class)],
            'minutes_retard' => ['nullable', 'integer', 'min:0', 'max:240'],
            'motif'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'minutes_retard.max' => 'Un retard ne peut pas dépasser 240 minutes.',
            'motif.max'          => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
