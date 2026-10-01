<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Une reponse dans un fil existant. Le droit d'ecrire dans CE fil precis se
 * verifie dans le service, qui connait le service destinataire et le tuteur
 * proprietaire ; ici on ne controle que le role.
 */
class StoreMessageRequest extends FormRequest
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
            'corps' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'corps.required' => 'Le message ne peut pas être vide.',
            'corps.max'      => 'Le message ne peut pas dépasser 5000 caractères.',
        ];
    }
}
