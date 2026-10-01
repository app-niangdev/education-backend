<?php

namespace App\Http\Requests;

use App\Enums\ModePaiementEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Encaissement d'une facture couvrant plusieurs mois. Deux modes de saisie :
 *
 *  - AUTOMATIQUE : le tresorier saisit le montant recu, le serveur l'impute en
 *    cascade sur les mois non soldes du plus ancien au plus recent (50 000 pour
 *    une mensualite de 20 000 solde deux mois et laisse 10 000 d'avance sur le
 *    troisieme) ;
 *  - SELECTION : le tresorier coche les mois et, au besoin, ajuste le montant
 *    impute sur chacun.
 *
 * Les plafonds reels (reste par mois, reste total) dependent de l'etat en base
 * et sont verifies dans le service, sous verrou, pour resister aux versements
 * concurrents.
 */
class PayerFactureMensualitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role?->name === 'treasurer';
    }

    public function rules(): array
    {
        return [
            'mode_repartition'   => ['required', Rule::in(['AUTOMATIQUE', 'SELECTION'])],

            // Requis en mode automatique ; ignore en mode selection, ou le
            // total decoule de la somme des lignes.
            'montant'            => ['nullable', 'integer', 'min:1'],

            'lignes'                 => ['nullable', 'array', 'min:1'],
            'lignes.*.mensualite_id' => ['required_with:lignes', 'integer', 'exists:mensualites,id'],
            'lignes.*.montant'       => ['required_with:lignes', 'integer', 'min:1'],

            'mode_paiement'      => ['required', Rule::enum(ModePaiementEnum::class)],
            'numero_transaction' => ['nullable', 'string', 'max:100'],
            'date_paiement'      => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('mode_repartition');

            if ($mode === 'AUTOMATIQUE' && ! $this->filled('montant')) {
                $validator->errors()->add(
                    'montant',
                    'Le montant versé est obligatoire pour une répartition automatique.'
                );
            }

            if ($mode !== 'SELECTION') {
                return;
            }

            $lignes = $this->input('lignes', []);

            if (empty($lignes)) {
                $validator->errors()->add(
                    'lignes',
                    'Sélectionnez au moins un mois à régler.'
                );

                return;
            }

            // Un meme mois deux fois sur la meme facture rendrait le total et
            // l'imputation ambigus : on l'interdit des la validation.
            $ids = array_column($lignes, 'mensualite_id');

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add(
                    'lignes',
                    'Un même mois ne peut figurer qu\'une seule fois sur la facture.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'mode_repartition.required'    => 'Le mode de répartition est obligatoire.',
            'mode_repartition.in'          => 'Le mode de répartition est invalide.',
            'montant.integer'              => 'Le montant versé doit être un nombre entier.',
            'montant.min'                  => 'Le montant versé doit être strictement supérieur à 0.',
            'lignes.min'                   => 'Sélectionnez au moins un mois à régler.',
            'lignes.*.mensualite_id.exists' => 'Un des mois sélectionnés est introuvable.',
            'lignes.*.montant.min'         => 'Le montant imputé sur un mois doit être strictement supérieur à 0.',
            'mode_paiement.required'       => 'Le mode de paiement est obligatoire.',
            'mode_paiement.enum'           => 'Le mode de paiement sélectionné est invalide.',
            'date_paiement.date'           => 'La date de paiement est invalide.',
        ];
    }
}
