<?php

namespace App\Http\Requests;

use App\Models\Classe;
use App\Models\Inscription;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreInscriptionRequest extends FormRequest
{
    /**
     * L'inscription est un acte de caisse : elle ouvre les frais et l'echeancier
     * que le tresorier encaissera. C'est donc lui qui la saisit, au guichet, dans
     * la foulee de la fiche eleve — le manager ne fait plus qu'en suivre la liste
     * (voir InscriptionController::index, ouvert plus largement).
     *
     * Le surveillant la garde : il recoit lui aussi les familles.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'treasurer', 'supervisor']);
    }

    public function rules(): array
    {
        return [
            'eleve_id'         => ['required', 'integer', 'exists:eleves,id'],
            'classe_id'        => ['required', 'integer', 'exists:classes,id'],
            'date_inscription' => ['nullable', 'date'],
        ];
    }

    /**
     * L'annee scolaire est portee par la classe : l'unicite
     * (eleve, annee_scolaire) ne peut se verifier qu'une fois les deux
     * identifiants valides.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $anneeScolaireId = Classe::whereKey($this->input('classe_id'))->value('annee_scolaire_id');

            $dejaInscrit = Inscription::where('eleve_id', $this->input('eleve_id'))
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->exists();

            if ($dejaInscrit) {
                $validator->errors()->add('eleve_id', "Cet élève est déjà inscrit pour cette année scolaire.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'eleve_id.required'     => "L'élève est obligatoire.",
            'eleve_id.exists'       => "L'élève sélectionné est invalide.",
            'classe_id.required'    => 'La classe est obligatoire.',
            'classe_id.exists'      => 'La classe sélectionnée est invalide.',
            'date_inscription.date' => "La date d'inscription est invalide.",
        ];
    }
}
