<?php

namespace App\Http\Requests;

use App\Models\Surveillant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveillantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager']);
    }

    public function rules(): array
    {
        $surveillantId = $this->route('id');

        // L'email est unique en base : sans ignorer l'utilisateur edite, toute
        // modification se heurterait a sa propre adresse. Sans regle du tout,
        // un doublon passerait la validation pour echouer en base.
        $userId = Surveillant::withTrashed()->find($surveillantId)?->user_id;

        return [
            'user.first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'user.last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'user.phone_one'  => ['sometimes', 'required', 'string', 'max:20'],
            'user.phone_two'  => ['nullable', 'string', 'max:20'],
            // Facultatif ici, alors qu'il est exige a la creation : des
            // comptes anterieurs a cette regle n'ont pas d'adresse, et la
            // rendre obligatoire en edition les figerait — plus aucune
            // correction de nom ou de telephone ne passerait tant qu'une
            // adresse n'aurait pas ete inventee pour eux.
            'user.email'      => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'user.address'    => ['nullable', 'string', 'max:255'],
            'user.status'     => ['nullable', 'boolean'],

            // Les conditions d'engagement ne se modifient plus ici : elles
            // appartiennent au contrat, qui les historise et se renouvelle.
            'profil.matricule'        => ['nullable', 'string', 'max:50', "unique:surveillants,matricule,{$surveillantId}"],
            'profil.zone_surveillance'=> ['nullable', 'string', 'max:255'],
            'profil.horaire'          => ['nullable', 'string', 'max:255'],
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
        ];
    }
}
