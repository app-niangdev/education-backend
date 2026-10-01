<?php

namespace App\Http\Requests;

use App\Models\Enseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnseignantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        $enseignantId = $this->route('id');

        // L'email est unique en base : sans ignorer l'utilisateur edite, toute
        // modification se heurterait a sa propre adresse. Sans regle du tout,
        // un doublon passerait la validation pour echouer en base.
        $userId = Enseignant::withTrashed()->find($enseignantId)?->user_id;

        return [
            'user.first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'user.last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'user.phone_one'  => ['sometimes', 'required', 'string', 'max:20'],
            'user.phone_two'  => ['nullable', 'string', 'max:20'],
            // Facultatif ici, alors qu'il est exige a la creation : des comptes
            // anterieurs a cette regle n'ont pas d'adresse, et la rendre
            // obligatoire en edition les figerait.
            'user.email'      => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'user.address'    => ['nullable', 'string', 'max:255'],
            'user.status'     => ['nullable', 'boolean'],

            // Les conditions d'engagement ne se modifient plus ici : elles
            // appartiennent au contrat, qui les historise et se renouvelle.
            'profil.matricule'     => ['nullable', 'string', 'max:50', "unique:enseignants,matricule,{$enseignantId}"],
            'profil.diplomes'      => ['nullable', 'string'],

            // Matières de spécialité : la liste envoyée remplace l'existante.
            'profil.matieres'      => ['nullable', 'array'],
            'profil.matieres.*'    => ['integer', 'distinct', 'exists:matieres,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user.first_name.required' => 'Le prénom est obligatoire.',
            'user.last_name.required'  => 'Le nom est obligatoire.',
            'user.phone_one.required'  => 'Le téléphone principal est obligatoire.',
            'user.email.email'         => "L'adresse email n'est pas valide.",
            'user.email.unique'        => "Cette adresse email est déjà utilisée.",
            'profil.matricule.unique'  => "Ce matricule est déjà attribué.",
            'profil.matieres.array'       => "Les matières de spécialité doivent être une liste.",
            'profil.matieres.*.exists'    => "Une des matières sélectionnées n'existe pas.",
            'profil.matieres.*.distinct'  => "Une matière ne peut être sélectionnée qu'une seule fois.",
        ];
    }
}
