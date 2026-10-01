<?php

namespace App\Http\Requests;

use App\Enums\TypeEvaluationEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager', 'teacher']);
    }

    public function rules(): array
    {
        return [
            'affectation_id'  => ['required', 'integer', 'exists:affectations,id'],
            'periode_id'      => ['required', 'integer', 'exists:periodes,id'],
            'titre'           => ['required', 'string', 'max:255'],
            'type'            => ['required', new Enum(TypeEvaluationEnum::class)],
            // Barème optionnel : par défaut celui de l'établissement (parametrages).
            'bareme'          => ['sometimes', 'integer', 'min:1', 'max:100'],
            'date_evaluation' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'affectation_id.required'  => "L'affectation (classe × matière) est obligatoire.",
            'affectation_id.exists'    => "L'affectation sélectionnée est invalide.",
            'periode_id.required'      => 'La période est obligatoire.',
            'periode_id.exists'        => 'La période sélectionnée est invalide.',
            'titre.required'           => "Le titre de l'évaluation est obligatoire.",
            'type.required'            => "Le type d'évaluation est obligatoire.",
            'bareme.min'               => 'Le barème doit être au moins de 1.',
            'date_evaluation.required' => "La date de l'évaluation est obligatoire.",
        ];
    }
}
