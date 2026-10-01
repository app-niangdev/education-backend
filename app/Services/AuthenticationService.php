<?php

namespace App\Services;

use App\Interfaces\LoginChallengeServiceInterface;
use App\Interfaces\LoginSecurityServiceInterface;
use App\Models\LoginChallenge;
use App\Models\Menu;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;

/**
 * L'enchainement d'une connexion, de bout en bout.
 *
 * Le controleur ne fait que traduire en HTTP ce que ce service decide. Les
 * regles tiennent en trois phrases :
 *
 *  1. Une IP bloquee ne parvient jamais ici — le middleware l'a deja renvoyee.
 *  2. Des identifiants faux incrementent le compteur d'echecs, et rien d'autre.
 *     Aucun code n'est genere ni envoye a ce stade : l'OTP protege les
 *     connexions reussies, il n'est pas un accuse de reception d'echec.
 *  3. Des identifiants justes ouvrent la session immediatement, sauf si la
 *     double authentification est active ou si l'IP sort d'un blocage : dans
 *     ces deux cas seulement, un code est exige avant tout jeton.
 *
 * Le jeton n'est emis qu'au bout de la chaine. Tant qu'un code est attendu,
 * l'utilisateur est reconnu mais pas authentifie, et ne detient qu'une
 * reference opaque vers son challenge.
 */
class AuthenticationService
{
    public const LOGIN_SUCCESS       = 'LOGIN_SUCCESS';
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const OTP_REQUIRED        = 'OTP_REQUIRED';
    public const ACCOUNT_DISABLED    = 'ACCOUNT_DISABLED';

    public const RAISON_TWO_FACTOR = 'TWO_FACTOR';
    public const RAISON_BLOCAGE    = 'IP_PREVIOUSLY_BLOCKED';

    private string $secret;
    private string $issuer;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(
        private readonly LoginSecurityServiceInterface $securite,
        private readonly LoginChallengeServiceInterface $challenges,
        private readonly SecurityLogger $journal,
    ) {
        $this->secret     = config('jwt.secret');
        $this->issuer     = config('app.url') . '/api';
        $this->accessTtl  = config('jwt.access_ttl', 18000);
        $this->refreshTtl = config('jwt.refresh_ttl', 2592000);
    }

    /**
     * Traite une demande de connexion.
     *
     * @param array<string, mixed> $credentials
     * @return array{code: string, data?: array<string, mixed>}
     */
    public function tenterConnexion(array $credentials, string $ip): array
    {
        if (! Auth::attempt($credentials)) {
            $etat = $this->securite->enregistrerEchec($ip);

            // Le refus ne dit pas si l'identifiant existe : distinguer « compte
            // inconnu » de « mot de passe faux » offrirait un moyen d'enumerer
            // les comptes de l'etablissement.
            return [
                'code' => self::INVALID_CREDENTIALS,
                'data' => [
                    'attempts_left' => $this->essaisRestants($etat->failed_attempts),
                ],
            ];
        }

        /** @var User $user */
        $user = Auth::user();

        // Un compte desactive ne franchit pas cette porte, mot de passe correct
        // ou non. L'echec se compte : sans cela, un compte suspendu offrirait
        // une cible a marteler sans limite.
        if (! $user->status) {
            $this->securite->enregistrerEchec($ip);

            $this->journal->log(SecurityLogger::LOGIN_FAILED, $user->id, [
                'reason' => 'account_disabled',
            ]);

            return ['code' => self::ACCOUNT_DISABLED];
        }

        $this->journal->log(SecurityLogger::LOGIN_SUCCESS, $user->id);

        $sortaitDeBlocage = $this->securite->aEteBloquee($ip);

        if ($sortaitDeBlocage) {
            $this->journal->log(SecurityLogger::IP_BLOCK_EXPIRED, $user->id);
        }

        $raison = match (true) {
            (bool) $user->two_factor_enabled => self::RAISON_TWO_FACTOR,
            $sortaitDeBlocage                => self::RAISON_BLOCAGE,
            default                          => null,
        };

        if ($raison !== null) {
            $challenge = $this->challenges->ouvrir($user, $ip, $raison);

            return [
                'code' => self::OTP_REQUIRED,
                'data' => [
                    'challenge_token' => $challenge->challenge_token,
                    'reason'          => $raison,
                    'expires_in'      => (int) config('login_security.otp.ttl', 300),
                    // De quoi rassurer sans exposer l'adresse complete.
                    'masked_email'    => $this->masquerEmail($user->email),
                ],
            ];
        }

        // Aucun second facteur requis : la connexion s'acheve ici.
        $this->securite->reinitialiser($ip);

        $this->journal->log(SecurityLogger::LOGIN_COMPLETED, $user->id, [
            'two_factor' => false,
        ]);

        return [
            'code' => self::LOGIN_SUCCESS,
            'data' => $this->ouvrirSession($user),
        ];
    }

    /**
     * Termine une connexion dont le code vient d'etre valide.
     *
     * @return array<string, mixed>
     */
    public function finaliser(LoginChallenge $challenge, string $ip): array
    {
        $user = $challenge->user;

        // Le passif de l'IP n'est solde qu'ici : c'est le seul point ou une
        // authentification est reellement complete. Le solder plus tot — des le
        // mot de passe accepte — permettrait de lever le blocage progressif
        // sans jamais fournir de code.
        $this->securite->reinitialiser($ip);

        $this->journal->log(SecurityLogger::LOGIN_COMPLETED, $user->id, [
            'challenge_id' => $challenge->id,
            'reason'       => $challenge->reason,
        ]);

        return $this->ouvrirSession($user);
    }

    /**
     * Le couple de jetons et ce que le client attend au demarrage.
     *
     * @return array<string, mixed>
     */
    public function ouvrirSession(User $user): array
    {
        [$accessToken, $refreshToken, $expiresIn] = $this->genererPaireDeJetons($user);

        $menus = Menu::query()
            ->whereHas('menuRoles', fn ($q) => $q->where('role_id', $user->role_id))
            ->orderBy('position')
            ->get();

        return [
            'access_token'         => $accessToken,
            'refresh_token'        => $refreshToken,
            'token_type'           => 'Bearer',
            'expires_in'           => $expiresIn,
            'must_change_password' => (bool) $user->must_change_password,
            'menus'                => $menus,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    public function genererPaireDeJetons(User $user): array
    {
        $now       = time();
        $expiresIn = $this->accessTtl;

        $accessPayload = [
            'iss'        => $this->issuer,
            'aud'        => $this->issuer,
            'iat'        => $now,
            'nbf'        => $now,
            'exp'        => $now + $expiresIn,
            'sub'        => $user->id,
            'jti'        => 'access_' . uniqid('', true),
            // Ce que l'ecran affiche avant meme d'avoir appele `/auth/me` :
            // l'en-tete et la barre laterale montrent le nom et l'avatar des
            // la premiere image rendue. Sans ces champs, le profil restait
            // vide jusqu'au retour de `me`, puis se remplissait d'un coup.
            'user'       => [
                'id'               => $user->id,
                'email'            => $user->email,
                'first_name'       => $user->first_name,
                'last_name'        => $user->last_name,
                'full_name'        => $user->full_name,
                'phone_number_one' => $user->phone_one,
                'phone_number_two' => $user->phone_two,
                'address'          => $user->address,
                'image_url'        => $user->image_url,
                'status'           => (bool) $user->status,
                'role'             => $user->role?->name,
                'menus'            => $user->menus ?? [],
            ],
            'token_type' => 'access',
        ];

        $refreshPayload = [
            'iss'        => $this->issuer,
            'aud'        => $this->issuer,
            'iat'        => $now,
            'nbf'        => $now,
            'exp'        => $now + $this->refreshTtl,
            'sub'        => $user->id,
            'jti'        => 'refresh_' . uniqid('', true),
            'token_type' => 'refresh',
        ];

        return [
            JWT::encode($accessPayload, $this->secret, 'HS256'),
            JWT::encode($refreshPayload, $this->secret, 'HS256'),
            $expiresIn,
        ];
    }

    /**
     * Essais restants avant blocage. Sert au message d'avertissement affiche
     * a l'utilisateur qui se trompe : la mesure est de toute facon deductible
     * en comptant, autant la rendre lisible plutot que de laisser le blocage
     * tomber sans preavis.
     */
    private function essaisRestants(int $echecs): int
    {
        $seuil = (int) config('login_security.max_attempts', 5);

        return max(0, $seuil - $echecs);
    }

    /** j***@domaine.com : assez pour reconnaitre sa boite, pas pour la deviner. */
    private function masquerEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domaine] = explode('@', $email, 2);

        $visible = mb_substr($local, 0, 1);

        return $visible . str_repeat('*', max(3, mb_strlen($local) - 1)) . '@' . $domaine;
    }
}
