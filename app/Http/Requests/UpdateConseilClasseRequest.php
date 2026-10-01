<?php

namespace App\Http\Requests;

use App\Enums\DecisionConseilEnum;
use App\Enums\DistinctionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Les deux blocs de cases à cocher du bulletin et les observations libres.
 *
 * Les trois champs sont nullables : un conseil peut ne rien cocher, ou
 * décocher ce qu'il avait retenu. `present` plutôt que `sometimes` pour que
 * l'envoi explicite d'un null soit accepté et efface la valeur.
 */
class UpdateConseilClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'decision_conseil' => ['nullable', new Enum(DecisionConseilEnum::class)],
            'distinction'      => ['nullable', new Enum(DistinctionEnum::class)],
            'observations'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_conseil.Illuminate\Validation\Rules\Enum' => "L'appréciation générale sélectionnée est invalide.",
            'distinction.Illuminate\Validation\Rules\Enum'      => 'La distinction sélectionnée est invalide.',
            'observations.max'                                  => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
