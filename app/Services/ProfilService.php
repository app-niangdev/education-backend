<?php

namespace App\Services;

use App\Interfaces\ProfilServiceInterface;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Le compte vu par son propre titulaire : ce qu'il peut y changer, et la forme
 * sous laquelle l'application le relit.
 *
 * `representer()` est partage par `/auth/me` et par la mise a jour : les deux
 * repondent le meme objet, si bien que l'ecran n'a jamais a rappeler `me`
 * apres un enregistrement pour se remettre a jour.
 */
class ProfilService implements ProfilServiceInterface
{
    /** Le profil metier attache a chaque role, quand il y en a un. */
    private const PROFILS = [
        'teacher'    => 'enseignant',
        'supervisor' => 'surveillant',
        'treasurer'  => 'tresorier',
    ];

    public function __construct(
        private readonly SecurityLogger $journal,
    ) {}

    public function mettreAJour(
        User $user,
        array $donnees,
        ?UploadedFile $photo = null,
        bool $supprimerPhoto = false
    ): User {
        // Coordonnees et photo tombent ensemble ou pas du tout : un
        // enregistrement qui laisserait le numero a jour mais l'ancienne photo
        // en place afficherait un profil que l'utilisateur n'a jamais valide.
        DB::transaction(function () use ($user, $donnees, $photo, $supprimerPhoto) {
            $user->update($donnees);

            if ($photo) {
                // Collection en singleFile() : l'ancienne photo et son fichier
                // partent d'eux-memes.
                $user->addMedia($photo)->toMediaCollection(User::COLLECTION_PHOTO);
            } elseif ($supprimerPhoto) {
                $user->clearMediaCollection(User::COLLECTION_PHOTO);
            }
        });

        $this->journal->log(SecurityLogger::PROFIL_UPDATED, $user->id, [
            'champs' => array_keys($donnees),
            'photo'  => $photo ? 'remplacee' : ($supprimerPhoto ? 'supprimee' : 'inchangee'),
        ]);

        // `fresh()` seul garderait en cache la collection media chargee avant
        // l'ecriture, et `image_url` renverrait l'ancienne photo.
        return $user->fresh(['role', 'media']);
    }

    public function representer(User $user): array
    {
        $user->loadMissing('role');

        $relation = self::PROFILS[$user->role?->name] ?? null;

        if ($relation) {
            // Le contrat en cours porte le type d'engagement et la date
            // d'embauche affiches sur la fiche : sans ce chargement, chaque
            // champ declencherait sa propre requete.
            $relations = ["{$relation}.contratActif"];

            // L'enseignant expose ses matieres de specialite sur sa fiche.
            if ($relation === 'enseignant') {
                $relations[] = 'enseignant.matieres';
            }

            $user->loadMissing($relations);
        }

        return [
            'id'                 => $user->id,
            'first_name'         => $user->first_name,
            'last_name'          => $user->last_name,
            'full_name'          => $user->full_name,
            'email'              => $user->email,
            // Les noms attendus par le front. Les colonnes s'appellent
            // `phone_one`/`phone_two` ; l'ecran, lui, lit
            // `phone_number_one`/`phone_number_two` depuis le jeton, et c'est
            // ce vocabulaire-la qui fait foi cote client.
            'phone_number_one'   => $user->phone_one,
            'phone_number_two'   => $user->phone_two,
            'address'            => $user->address,
            'image_url'          => $user->image_url,
            'status'             => $user->status,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'role'               => $user->role?->name,
            'profil'             => $relation ? $user->{$relation} : null,
            'menus'              => $this->menus($user),
        ];
    }

    /**
     * Les menus du role, renvoyes a chaque lecture du profil pour que le front
     * rafraichisse son cache local : un menu ajoute ou renomme cote serveur
     * apparait sans imposer une reconnexion.
     */
    private function menus(User $user): \Illuminate\Support\Collection
    {
        return Menu::query()
            ->whereHas('menuRoles', fn ($q) => $q->where('role_id', $user->role_id))
            ->orderBy('position')
            ->get();
    }
}
