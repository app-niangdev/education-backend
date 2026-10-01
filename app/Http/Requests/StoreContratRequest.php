<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValideLeContrat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contrat cree hors creation de profil : reprise d'un employe dont le contrat
 * precedent est clos, ou saisie d'un engagement omis.
 */
class StoreContratRequest extends FormRequest
{
    use ValideLeContrat;

    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        // Les champs du contrat sont ici a la racine du corps, d'ou le prefixe
        // vide. Le type est un mot-cle que le service traduit en modele : la
        // requete ne designe jamais une classe directement.
        return array_merge($this->reglesContrat(''), [
            'contractable_type' => ['required', 'in:enseignant,tresorier,surveillant'],
            'contractable_id'   => ['required', 'integer', 'min:1'],
            'date_debut'        => ['required', 'date'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->messagesContrat(''), [
            'contractable_type.required' => 'Le type de personnel est obligatoire.',
            'contractable_type.in'       => 'Le type de personnel doit être : enseignant, trésorier ou surveillant.',
            'contractable_id.required'   => 'Le membre du personnel est obligatoire.',
            'date_debut.required'        => 'La date de début du contrat est obligatoire.',
        ]);
    }
}
