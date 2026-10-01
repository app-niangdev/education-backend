<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValideLeContrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnseignantRequest extends FormRequest
{
    use ValideLeContrat;

    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return array_merge($this->reglesContrat(), [
            // Données utilisateur
            'user.first_name' => ['required', 'string', 'max:255'],
            'user.last_name'  => ['required', 'string', 'max:255'],
            'user.phone_one'  => ['required', 'string', 'max:20'],
            'user.phone_two'  => ['nullable', 'string', 'max:20'],
            // L'adresse est l'identifiant de connexion du personnel et la voie
            // par laquelle partent le mot de passe provisoire et les codes de
            // verification : sans elle, le compte cree serait inaccessible.
            'user.email'      => [
                'required', 'email', 'max:255',
                // Aligne sur l'index partiel `users_email_unique_active` : une
                // adresse liberee par un compte revoque redevient disponible.
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'user.address'    => ['nullable', 'string', 'max:255'],

            // Données profil : ce qui décrit la personne et sa qualification.
            // Les conditions d'engagement sont portées par le bloc `contrat`.
            'profil.matricule'     => ['nullable', 'string', 'max:50', 'unique:enseignants,matricule'],
            'profil.diplomes'      => ['nullable', 'string'],

            // Matières de spécialité : les matières que l'enseignant est
            // qualifié à enseigner (indépendant des affectations en classe).
            'profil.matieres'      => ['nullable', 'array'],
            'profil.matieres.*'    => ['integer', 'distinct', 'exists:matieres,id'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->messagesContrat(), [
            'user.first_name.required' => 'Le prénom est obligatoire.',
            'user.last_name.required'  => 'Le nom est obligatoire.',
            'user.phone_one.required'  => 'Le téléphone principal est obligatoire.',
            'user.email.required'      => "L'adresse email est obligatoire.",
            'user.email.email'         => "L'adresse email n'est pas valide.",
            'user.email.unique'        => "Cette adresse email est déjà utilisée.",
            'profil.matricule.unique'  => "Ce matricule est déjà attribué.",
            'profil.matieres.array'       => "Les matières de spécialité doivent être une liste.",
            'profil.matieres.*.exists'    => "Une des matières sélectionnées n'existe pas.",
            'profil.matieres.*.distinct'  => "Une matière ne peut être sélectionnée qu'une seule fois.",
        ]);
    }
}
