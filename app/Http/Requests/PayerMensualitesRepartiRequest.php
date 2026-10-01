<?php

namespace App\Http\Requests;

use App\Enums\ModePaiementEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayerMensualitesRepartiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role?->name === 'treasurer';
    }

    /**
     * Le plafond « montant <= reste total » depend de l'etat en base et est
     * verifie dans le service, sous verrou, pour resister aux versements
     * concurrents. Le montant est reparti sur les mois les plus anciens.
     */
    public function rules(): array
    {
        return [
            'montant'            => ['required', 'integer', 'min:1'],
            'mode_paiement'      => ['required', Rule::enum(ModePaiementEnum::class)],
            'numero_transaction' => ['nullable', 'string', 'max:100'],
            'date_paiement'      => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant.required'       => 'Le montant versé est obligatoire.',
            'montant.integer'        => 'Le montant versé doit être un nombre entier.',
            'montant.min'            => 'Le montant versé doit être strictement supérieur à 0.',
            'mode_paiement.required' => 'Le mode de paiement est obligatoire.',
            'mode_paiement.enum'     => 'Le mode de paiement sélectionné est invalide.',
            'date_paiement.date'     => 'La date de paiement est invalide.',
        ];
    }
}
