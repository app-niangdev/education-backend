<?php

namespace App\Services;

use App\Interfaces\PasswordResetServiceInterface;
use App\Mail\ActivationCompteMail;
use App\Mail\ResetPasswordMail;
use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * La reinitialisation d'un mot de passe oublie.
 *
 * Le jeton envoye par e-mail remplace momentanement la connaissance du mot de
 * passe : quiconque le detient peut prendre la main sur le compte. Il est donc
 * traite comme un secret a part entiere — aleatoire, stocke hache, valable une
 * heure, et consomme des qu'il a servi.
 *
 * La table `password_reset_tokens` a l'e-mail pour cle primaire : une nouvelle
 * demande ecrase la precedente, et un seul lien vaut a la fois.
 */
class PasswordResetService implements PasswordResetServiceInterface
{
    public const RESET_LINK_SENT       = 'RESET_LINK_SENT';
    public const RESET_ACCOUNT_UNKNOWN = 'RESET_ACCOUNT_UNKNOWN';
    public const RESET_THROTTLED       = 'RESET_THROTTLED';
    public const PASSWORD_RESET        = 'PASSWORD_RESET';
    public const RESET_TOKEN_INVALID   = 'RESET_TOKEN_INVALID';
    public const RESET_TOKEN_EXPIRED   = 'RESET_TOKEN_EXPIRED';

    public const ACTIVATION_LINK_SENT  = 'ACTIVATION_LINK_SENT';
    public const ACTIVATION_IMPOSSIBLE = 'ACTIVATION_IMPOSSIBLE';

    public const REINIT_ADMIN_SENT       = 'REINIT_ADMIN_SENT';
    public const REINIT_ADMIN_SANS_EMAIL = 'REINIT_ADMIN_SANS_EMAIL';
    public const REINIT_ADMIN_INACTIF    = 'REINIT_ADMIN_INACTIF';

    private const TABLE = 'password_reset_tokens';

    /** Les deux usages du meme mecanisme de jeton. */
    private const TYPE_RESET      = 'reset';
    private const TYPE_ACTIVATION = 'activation';

    /**
     * Validite du lien d'activation, en heures.
     *
     * Bien plus long que le lien d'oubli : celui-ci repond a une demande
     * deliberee, celui-la arrive sans prevenir dans une boite qui ne sera
     * peut-etre relevee que le lundi suivant.
     */
    private const ACTIVATION_VALIDITE_HEURES = 72;

    public function __construct(
        private readonly SecurityLogger $journal
    ) {}

    public function envoyerLien(string $email, string $ip): string
    {
        $this->journal->log(SecurityLogger::PASSWORD_RESET_REQUESTED, null, [
            'email_hash' => $this->empreinte($email),
        ]);

        $user = User::query()->where('email', $email)->first();

        // Adresse inconnue, compte desactive ou supprime : on s'arrete sans
        // rien envoyer. Le controleur repondra malgre tout « si un compte
        // existe, un lien a ete envoye » — sinon ce formulaire dirait qui est
        // inscrit, et lesquels de ces comptes sont encore actifs.
        if (! $user || ! $user->status) {
            return self::RESET_ACCOUNT_UNKNOWN;
        }

        // Un lien recent est encore valable : le renvoyer en boucle ferait de ce
        // formulaire un moyen d'inonder la boite de quelqu'un d'autre.
        $existant = DB::table(self::TABLE)->where('email', $email)->first();

        if ($existant && $this->tropRecent($existant->created_at)) {
            return self::RESET_THROTTLED;
        }

        $token = Str::random(64);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $email],
            [
                // Hache, comme un mot de passe : la table ne doit pas suffire a
                // prendre la main sur les comptes qui y figurent.
                'token'      => Hash::make($token),
                'type'       => self::TYPE_RESET,
                'created_at' => Carbon::now(),
            ]
        );

        $this->envoyer($user, $token);

        $this->journal->log(SecurityLogger::PASSWORD_RESET_SENT, $user->id);

        return self::RESET_LINK_SENT;
    }

    /**
     * Emet le lien qui ouvre un compte fraichement cree.
     *
     * Le compte nait sans mot de passe utilisable : ce lien est le seul moyen
     * d'y entrer la premiere fois. Il est donc emis sans etre soumis au delai
     * anti-rejeu de l'oubli — l'administrateur qui cree un compte, ou qui
     * renvoie le lien parce que le premier s'est perdu, ne doit pas se heurter
     * a une temporisation pensee pour un formulaire ouvert a tous.
     */
    public function envoyerLienActivation(User $user): string
    {
        // Sans adresse, rien a envoyer. Le cas ne devrait plus se presenter
        // pour le personnel — l'email y est obligatoire — mais un compte
        // anterieur a cette regle peut encore en manquer.
        if (! $user->email) {
            return self::ACTIVATION_IMPOSSIBLE;
        }

        // `status` est lu en base et non sur l'instance : un compte tout juste
        // cree ne porte pas encore le defaut de la colonne, et le refuser ici
        // priverait de lien celui-la meme pour qui il est fait. Seul un compte
        // explicitement desactive est ecarte.
        $actif = DB::table('users')->where('id', $user->id)->value('status');

        if ($actif === false) {
            return self::ACTIVATION_IMPOSSIBLE;
        }

        $token = Str::random(64);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => Hash::make($token),
                'type'       => self::TYPE_ACTIVATION,
                'created_at' => Carbon::now(),
            ]
        );

        $url = $this->lien($user, $token, self::TYPE_ACTIVATION);

        Log::channel('security')->info('Envoi du lien d\'activation en cours', [
            'user_id' => $user->id,
            'mailer'  => config('mail.default'),
        ]);

        try {
            // Envoi direct : sans worker de queue actif, un envoi en file
            // d'attente resterait en base sans jamais partir, et personne ne
            // le remarquerait avant que l'interesse signale ne rien avoir
            // recu. L'administrateur attend ici la confirmation reelle.
            Mail::to($user->email)->send(
                new ActivationCompteMail($user, $url, self::ACTIVATION_VALIDITE_HEURES)
            );

            Log::channel('security')->info('Lien d\'activation envoye', [
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            // Le message d'une exception de transport (hote injoignable,
            // authentification refusee, port ferme...) ne porte jamais le
            // corps du courriel : seul le transport lui-meme l'a vu. On peut
            // donc le journaliser sans risque, contrairement au lien.
            Log::channel('security')->error('Echec d\'envoi du lien d\'activation', [
                'user_id'   => $user->id,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
        }

        $this->journal->log(SecurityLogger::ACTIVATION_LINK_SENT, $user->id);

        return self::ACTIVATION_LINK_SENT;
    }

    /**
     * Emet un lien de reinitialisation a la demande de l'administration.
     *
     * Distinct de `envoyerLien` sur deux points, parce que l'appelant n'est pas
     * le meme. Le formulaire d'oubli est ouvert a tous : il tait l'existence du
     * compte et temporise pour ne pas servir a inonder une boite. Ici c'est un
     * manager identifie qui agit sur un compte qu'il administre deja — il a
     * droit a une reponse franche, et la temporisation ne le protegerait de
     * rien qu'il ne puisse deja faire.
     *
     * Le mot de passe n'est pas change ici : il le sera quand le titulaire
     * aura suivi le lien. L'ancien reste donc valable jusque-la, ce qui evite
     * de couper l'acces de quelqu'un sur un clic malencontreux.
     */
    public function envoyerLienAdministratif(User $user, User $auteur): string
    {
        if (! $user->email) {
            return self::REINIT_ADMIN_SANS_EMAIL;
        }

        // Un compte desactive ne doit pas pouvoir se redonner un acces : le
        // reactiver est une decision distincte, qui se prend ailleurs.
        if (! $user->status) {
            return self::REINIT_ADMIN_INACTIF;
        }

        $token = Str::random(64);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => Hash::make($token),
                'type'       => self::TYPE_RESET,
                'created_at' => Carbon::now(),
            ]
        );

        $this->envoyer($user, $token);

        $this->journal->log(SecurityLogger::PASSWORD_RESET_SENT, $user->id, [
            'origine'   => 'administration',
            'auteur_id' => $auteur->id,
        ]);

        return self::REINIT_ADMIN_SENT;
    }

    public function reinitialiser(string $email, string $token, string $motDePasse, string $ip): string
    {
        $ligne = DB::table(self::TABLE)->where('email', $email)->first();

        if (! $ligne || ! Hash::check($token, $ligne->token)) {
            $this->journal->log(SecurityLogger::PASSWORD_RESET_FAILED, null, [
                'email_hash' => $this->empreinte($email),
                'raison'     => 'token_invalide',
            ]);

            return self::RESET_TOKEN_INVALID;
        }

        if ($this->expire($ligne->created_at, $ligne->type ?? self::TYPE_RESET)) {
            // Un jeton perime ne resservira pas : on le retire tout de suite.
            DB::table(self::TABLE)->where('email', $email)->delete();

            $this->journal->log(SecurityLogger::PASSWORD_RESET_FAILED, null, [
                'email_hash' => $this->empreinte($email),
                'raison'     => 'token_expire',
            ]);

            return self::RESET_TOKEN_EXPIRED;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->status) {
            DB::table(self::TABLE)->where('email', $email)->delete();

            return self::RESET_TOKEN_INVALID;
        }

        $estActivation = ($ligne->type ?? self::TYPE_RESET) === self::TYPE_ACTIVATION;

        DB::transaction(function () use ($user, $motDePasse, $email, $estActivation) {
            $champs = [
                'password' => Hash::make($motDePasse),
                // Le mot de passe vient d'etre choisi par son proprietaire :
                // lui redemander d'en changer a la connexion n'aurait pas de sens.
                'must_change_password' => false,
            ];

            // Cliquer le lien prouve que l'adresse est bien relevee par le
            // titulaire du compte : l'activation vaut verification.
            if ($estActivation && ! $user->email_verified_at) {
                $champs['email_verified_at'] = Carbon::now();
            }

            $user->forceFill($champs)->save();

            // Usage unique : le lien ne doit pas pouvoir reprendre la main sur
            // le compte une seconde fois.
            DB::table(self::TABLE)->where('email', $email)->delete();

            // Les connexions laissees en attente d'un code appartiennent a
            // l'ancien mot de passe. Les laisser ouvertes offrirait a qui les
            // detient une session posterieure au changement.
            LoginChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('verified_at')
                ->update(['expires_at' => Carbon::now()]);
        });

        $this->journal->log(SecurityLogger::PASSWORD_RESET_COMPLETED, $user->id);

        return self::PASSWORD_RESET;
    }

    /**
     * Chaque type a sa peremption : purger tout au bout d'une heure
     * emporterait les liens d'activation encore vivants, et le nouvel employe
     * verrait son lien mourir avant meme d'avoir ouvert sa boite.
     */
    public function purger(): int
    {
        $supprimes = DB::table(self::TABLE)
            ->where('type', self::TYPE_ACTIVATION)
            ->where('created_at', '<', Carbon::now()->subHours(self::ACTIVATION_VALIDITE_HEURES))
            ->delete();

        $supprimes += DB::table(self::TABLE)
            ->where('type', '!=', self::TYPE_ACTIVATION)
            ->where('created_at', '<', Carbon::now()->subMinutes($this->dureeValidite()))
            ->delete();

        return $supprimes;
    }

    /**
     * Duree de validite d'un lien, en minutes.
     *
     * Reprend le reglage du broker Laravel (config/auth.php) pour que les deux
     * ne divergent pas.
     */
    private function dureeValidite(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }

    /** Delai minimal entre deux demandes, en secondes. */
    private function delaiEntreDemandes(): int
    {
        return (int) config('auth.passwords.users.throttle', 60);
    }

    private function tropRecent(?string $creeLe): bool
    {
        if (! $creeLe) {
            return false;
        }

        return Carbon::parse($creeLe)
            ->addSeconds($this->delaiEntreDemandes())
            ->isFuture();
    }

    /**
     * Un lien d'activation vit trois jours, un lien d'oubli une heure. La
     * duree se lit sur le jeton lui-meme, et non sur l'etat du compte : le
     * sort d'un lien deja parti ne doit pas changer sous les pieds de son
     * destinataire.
     */
    private function expire(?string $creeLe, string $type = self::TYPE_RESET): bool
    {
        if (! $creeLe) {
            return true;
        }

        $emis = Carbon::parse($creeLe);

        $peremption = $type === self::TYPE_ACTIVATION
            ? $emis->addHours(self::ACTIVATION_VALIDITE_HEURES)
            : $emis->addMinutes($this->dureeValidite());

        return $peremption->isPast();
    }

    /**
     * Achemine le lien.
     *
     * L'echec d'envoi n'est pas remonte a l'appelant : le distinguer d'un envoi
     * reussi renseignerait sur l'existence du compte.
     */
    private function envoyer(User $user, string $token): void
    {
        $url = $this->lien($user, $token, self::TYPE_RESET);

        try {
            Mail::to($user->email)->send(
                new ResetPasswordMail($user, $url, $this->dureeValidite())
            );
        } catch (\Throwable $e) {
            // Le message du transporteur peut contenir le corps du courriel,
            // donc le lien : on ne journalise que la classe de l'erreur.
            Log::channel('security')->error('Echec d\'envoi du lien de reinitialisation', [
                'user_id'   => $user->id,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * L'URL de l'ecran ou le mot de passe se choisit.
     *
     * Le meme ecran sert les deux cas : ce qui change est le discours qui
     * l'entoure — « choisissez votre mot de passe » a l'ouverture d'un compte,
     * « choisissez-en un nouveau » apres un oubli. Le parametre `activation`
     * le lui dit, sans quoi il accueillerait un nouvel employe par un message
     * de reinitialisation qui ne correspond a rien pour lui.
     */
    private function lien(User $user, string $token, string $type): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            . '/reset-password?token=' . urlencode($token)
            . '&email=' . urlencode($user->email);

        return $type === self::TYPE_ACTIVATION
            ? $url . '&activation=1'
            : $url;
    }

    /**
     * Empreinte courte d'une adresse, pour le journal.
     *
     * Permet de relier les lignes d'une meme campagne sans ecrire en clair
     * l'adresse de quelqu'un qui n'a peut-etre pas de compte ici.
     */
    private function empreinte(string $email): string
    {
        return substr(hash('sha256', mb_strtolower($email)), 0, 12);
    }
}
