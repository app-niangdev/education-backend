<?php

namespace App\Http\Requests;

use App\Enums\TypeEvaluationEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager', 'teacher']);
    }

    /**
     * L'affectation d'une évaluation n'est pas modifiable après coup (elle
     * détermine la classe et donc les élèves concernés) : on ne modifie que
     * ses caractéristiques et sa période.
     */
    public function rules(): array
    {
        return [
            'periode_id'      => ['sometimes', 'integer', 'exists:periodes,id'],
            'titre'           => ['sometimes', 'string', 'max:255'],
            'type'            => ['sometimes', new Enum(TypeEvaluationEnum::class)],
            'bareme'          => ['sometimes', 'integer', 'min:1', 'max:100'],
            'date_evaluation' => ['sometimes', 'date'],
        ];
    }
}
