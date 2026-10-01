<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\CompteTuteurServiceInterface;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ouverture d'un acces de connexion pour un tuteur.
 *
 * La fiche tuteur et le compte utilisateur restent deux choses distinctes : la
 * fiche existe des l'inscription de l'enfant, le compte ne se cree qu'a la
 * demande. Beaucoup de familles n'en auront jamais — pas d'adresse e-mail, pas
 * d'usage du numerique — et une inscription ne doit jamais dependre de cela.
 *
 * Le mot de passe est genere aleatoirement et renvoye UNE SEULE FOIS, a
 * l'agent qui cree le compte : il n'est stocke nulle part en clair. C'est a
 * l'ecole de le transmettre a la famille, par le canal qu'elle juge sur.
 */
class CompteTuteurService implements CompteTuteurServiceInterface
{
    public function __construct(
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    /**
     * @return array{tuteur: Tuteur, mot_de_passe: string}
     */
    public function creer(int|string $tuteurId, array $data, User $authUser): array
    {
        $tuteur = Tuteur::findOrFail($tuteurId);

        if ($tuteur->user_id !== null) {
            abort(422, 'Ce tuteur dispose déjà d\'un compte.');
        }

        // Le telephone est l'identifiant de connexion : c'est lui qui est
        // exige, pas l'adresse e-mail. Beaucoup de familles n'en ont pas, et
        // en faire une condition fermerait la messagerie a celles-la meme
        // qu'elle doit servir.
        $telephone = Tuteur::normaliserTelephone($data['telephone'] ?? $tuteur->telephone_principal);

        if (empty($telephone)) {
            abort(422, "Un numéro de téléphone est nécessaire : il sert d'identifiant de connexion.");
        }

        // withTrashed() : le soft-delete ne libere pas un identifiant. Sans
        // cela, deux comptes pourraient repondre au meme numero et
        // Auth::attempt en choisirait un au hasard.
        if (User::withTrashed()->where('phone_one', $telephone)->exists()) {
            abort(422, "Ce numéro de téléphone est déjà utilisé par un autre compte, actif ou révoqué.");
        }

        // L'e-mail reste facultatif, mais s'il est fourni il doit rester
        // unique : la colonne porte une contrainte, et c'est par lui que
        // passe « mot de passe oublié ».
        $email = $data['email'] ?? $tuteur->email;

        if (filled($email) && User::withTrashed()->where('email', $email)->exists()) {
            abort(422, "Cette adresse e-mail est déjà utilisée par un autre compte, actif ou révoqué.");
        }

        // Assez long pour ne pas se deviner, assez court pour se dicter au
        // telephone : le premier usage sera souvent une lecture a voix haute.
        $motDePasse = Str::password(12, symbols: false);

        $tuteur = DB::transaction(function () use ($tuteur, $email, $telephone, $motDePasse, $authUser) {
            $user = User::create([
                'first_name'           => $tuteur->prenom,
                'last_name'            => $tuteur->nom,
                // Peut rester nul : le telephone suffit a se connecter.
                'email'                => filled($email) ? $email : null,
                'password'             => $motDePasse,
                'username'             => $this->genererIdentifiant($tuteur),
                // Sous forme normalisee : c'est ce que compare Auth::attempt.
                'phone_one'            => $telephone,
                'phone_two'            => $tuteur->telephone_secondaire,
                'address'              => $tuteur->adresse,
                'status'               => true,
                'role_id'              => RoleEnum::Tuteur->value,
                // Le mot de passe transite par un tiers (l'agent, puis un SMS
                // ou une remise en main propre) : il doit changer au premier
                // usage.
                'must_change_password' => true,
            ]);

            // La fiche est realignee sur ce qui sert a se connecter : sans
            // cela, corriger un numero au moment de la creation du compte
            // laisserait la fiche sur l'ancien.
            $tuteur->update([
                'user_id'             => $user->id,
                'email'               => filled($email) ? $email : $tuteur->email,
                'telephone_principal' => $telephone,
            ]);

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'tuteurs',
                description: "Ouverture d'un accès pour le tuteur « {$tuteur->nom_complet} »",
                subject:     $tuteur,
            );

            return $tuteur->fresh(['user']);
        });

        return ['tuteur' => $tuteur, 'mot_de_passe' => $motDePasse];
    }

    /**
     * Remet un mot de passe a zero : la famille a perdu le sien. Le compte
     * n'est pas recree, seuls les identifiants changent.
     *
     * @return array{tuteur: Tuteur, mot_de_passe: string}
     */
    public function reinitialiserMotDePasse(int|string $tuteurId, User $authUser): array
    {
        $tuteur = Tuteur::with('user')->findOrFail($tuteurId);

        if ($tuteur->user === null) {
            abort(422, "Ce tuteur n'a pas de compte : il n'y a pas de mot de passe à réinitialiser.");
        }

        $motDePasse = Str::password(12, symbols: false);

        $tuteur->user->update([
            'password'             => $motDePasse,
            'must_change_password' => true,
        ]);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'tuteurs',
            description: "Réinitialisation du mot de passe du tuteur « {$tuteur->nom_complet} »",
            subject:     $tuteur,
        );

        return ['tuteur' => $tuteur->fresh(['user']), 'mot_de_passe' => $motDePasse];
    }

    /** Ferme l'acces sans toucher a la fiche : les eleves y restent rattaches. */
    public function revoquer(int|string $tuteurId, User $authUser): Tuteur
    {
        $tuteur = Tuteur::with('user')->findOrFail($tuteurId);

        if ($tuteur->user === null) {
            abort(422, "Ce tuteur n'a pas de compte.");
        }

        DB::transaction(function () use ($tuteur, $authUser) {
            $user = $tuteur->user;

            // La fiche est deliee AVANT la suppression : la contrainte est en
            // nullOnDelete, mais l'ordre explicite evite de dependre d'elle.
            $tuteur->update(['user_id' => null]);
            $user->delete();

            $this->activityLog->log(
                user:        $authUser,
                action:      'deleted',
                module:      'tuteurs',
                description: "Révocation de l'accès du tuteur « {$tuteur->nom_complet} »",
                subject:     $tuteur,
            );
        });

        return $tuteur->fresh();
    }

    /**
     * Un identifiant lisible, unique. Le suffixe numerique n'est pose qu'en
     * cas de collision : deux « moussa.diop » dans un etablissement n'a rien
     * d'improbable.
     *
     * withTrashed() est essentiel : « users » porte un soft-delete, et un
     * compte revoque occupe toujours son identifiant. Sans cela, le tuteur
     * suivant se verrait attribuer l'identifiant d'un compte desactive — deux
     * lignes partageraient alors la meme valeur, ce que la base n'interdit
     * pas (seul « email » porte une contrainte d'unicite).
     */
    private function genererIdentifiant(Tuteur $tuteur): string
    {
        $base = Str::slug("{$tuteur->prenom}.{$tuteur->nom}", '.');
        $identifiant = $base;
        $suffixe = 1;

        while (User::withTrashed()->where('username', $identifiant)->exists()) {
            $identifiant = "{$base}{$suffixe}";
            $suffixe++;
        }

        return $identifiant;
    }
}
