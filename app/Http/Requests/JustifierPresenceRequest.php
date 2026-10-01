<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Justifier une absence, avec le document éventuel.
 *
 * Réservé à la vie scolaire : l'enseignant constate l'absence, il ne décide
 * pas de sa légitimité.
 *
 * Le plafond de 2 Mo est calé sur `upload_max_filesize` du serveur — au-delà,
 * PHP tronquerait la requête avant Laravel et l'utilisateur verrait une erreur
 * incompréhensible plutôt que ce message.
 */
class JustifierPresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager', 'supervisor'], true);
    }

    public function rules(): array
    {
        return [
            'justifie'     => ['required', 'boolean'],
            'motif'        => ['nullable', 'string', 'max:500'],
            'justificatif' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'justifie.required'   => 'Précisez si cette absence est justifiée.',
            'motif.max'           => 'Le motif ne peut pas dépasser 500 caractères.',
            'justificatif.mimes'  => 'Le justificatif doit être un PDF ou une image (JPG, PNG).',
            'justificatif.max'    => 'Le justificatif ne peut pas dépasser 2 Mo.',
        ];
    }
}
