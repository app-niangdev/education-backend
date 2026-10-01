<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValideLeContrat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Prolongation d'un engagement par un nouveau contrat.
 *
 * Tous les champs sont facultatifs : le contrat cree reprend les conditions du
 * precedent, et la requete ne mentionne que ce qui change — le plus souvent les
 * seules dates. La date de debut, si elle est omise, suit le terme de l'ancien.
 */
class RenouvelerContratRequest extends FormRequest
{
    use ValideLeContrat;

    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return $this->reglesContrat('');
    }

    public function messages(): array
    {
        return $this->messagesContrat('');
    }
}
