<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValideLeContrat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correction des conditions d'un contrat en cours.
 *
 * L'employe rattache n'est pas modifiable : transferer un contrat d'une personne
 * a une autre reviendrait a reecrire son histoire. Le statut non plus, car
 * resilier et renouveler passent par leurs propres operations, qui portent
 * chacune leurs regles.
 */
class UpdateContratRequest extends FormRequest
{
    use ValideLeContrat;

    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return array_merge($this->reglesContrat(''), [
            // Suspendre puis reprendre un contrat se fait ici : c'est le seul
            // changement de statut reversible.
            'statut' => ['nullable', 'in:ACTIF,SUSPENDU'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->messagesContrat(''), [
            'statut.in' => "Le statut ne peut être que : actif ou suspendu. Utilisez la résiliation ou le renouvellement pour clore un contrat.",
        ]);
    }
}
