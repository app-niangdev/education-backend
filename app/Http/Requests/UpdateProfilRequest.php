<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ce qu'un utilisateur peut changer sur son propre compte : son prenom, son
 * nom, ses coordonnees et sa photo. Ni son role ni son statut n'y figurent —
 * ceux-la relevent de l'administration, et les laisser passer ici ouvrirait
 * une escalade de privileges par un simple champ de formulaire.
 */
class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La route est deja derriere `jwt.auth` : quiconque arrive ici modifie
        // son propre compte, jamais celui d'un autre (l'id vient du jeton).
        return $this->user() !== null;
    }

    /**
     * Le formulaire parle la langue du front (`phone_number_one`), la base
     * celle de la colonne (`phone_one`). La traduction se fait une seule fois,
     * dans `donneesValidees()`, pour que ni l'ecran ni le service n'aient a
     * connaitre le vocabulaire de l'autre.
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone_number_one' => [
                'required', 'string', 'min:9', 'max:20',
                // Le numero sert d'identifiant de connexion et porte un index
                // unique partiel : on exclut sa propre ligne, sinon un
                // enregistrement sans changement de numero serait refuse.
                Rule::unique('users', 'phone_one')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],
            'phone_number_two' => ['nullable', 'string', 'min:9', 'max:20'],
            'address'          => ['nullable', 'string', 'max:255'],
            'photo'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            // Retrait explicite de la photo existante : un champ vide ne suffit
            // pas a le dire, `photo` absent signifiant « ne touche a rien ».
            'supprimer_photo'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'       => 'Le prénom est obligatoire.',
            'first_name.max'            => 'Le prénom ne peut pas dépasser 100 caractères.',
            'last_name.required'        => 'Le nom est obligatoire.',
            'last_name.max'             => 'Le nom ne peut pas dépasser 100 caractères.',
            'phone_number_one.required' => 'Le téléphone principal est obligatoire.',
            'phone_number_one.min'      => 'Le téléphone principal doit contenir au moins 9 caractères.',
            'phone_number_one.max'      => 'Le téléphone principal ne peut pas dépasser 20 caractères.',
            'phone_number_one.unique'   => 'Ce numéro est déjà utilisé par un autre compte.',
            'phone_number_two.min'      => 'Le téléphone secondaire doit contenir au moins 9 caractères.',
            'phone_number_two.max'      => 'Le téléphone secondaire ne peut pas dépasser 20 caractères.',
            'address.max'               => "L'adresse ne peut pas dépasser 255 caractères.",
            'photo.image'               => 'La photo doit être une image.',
            'photo.mimes'               => 'La photo doit être au format jpeg, png, jpg ou webp.',
            'photo.max'                 => 'La photo ne peut pas dépasser 2 Mo.',
        ];
    }

    /**
     * L'identite et les coordonnees, sous leurs noms de colonnes.
     *
     * @return array{first_name: string, last_name: string, phone_one: string, phone_two: ?string, address: ?string}
     */
    public function donneesValidees(): array
    {
        $valides = $this->validated();

        return [
            'first_name' => $valides['first_name'],
            'last_name'  => $valides['last_name'],
            'phone_one'  => $valides['phone_number_one'],
            // Un champ vide efface la valeur : l'utilisateur qui retire son
            // second numero doit le voir disparaitre, pas rester en place.
            'phone_two'  => $this->valeurOuNull($valides['phone_number_two'] ?? null),
            'address'    => $this->valeurOuNull($valides['address'] ?? null),
        ];
    }

    private function valeurOuNull(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }
}
