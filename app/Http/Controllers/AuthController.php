<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\TwoFactorRequest;
use App\Http\Requests\UpdateProfilRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Interfaces\LoginChallengeServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\ProfilServiceInterface;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\LoginChallengeService;
use App\Services\PasswordResetService;
use App\Services\SecurityLogger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Sert a relire un refresh token. La fabrication vit dans le service. */
    private string $secret;

    public function __construct(
        private readonly AuthenticationService $authentification,
        private readonly LoginChallengeServiceInterface $challenges,
        private readonly PasswordResetServiceInterface $reinitialisation,
        private readonly ProfilServiceInterface $profils,
        private readonly SecurityLogger $journal,
    ) {
        $this->secret = config('jwt.secret');   // APP_JWT_SECRET dans .env
    }

    // -------------------------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------------------------
    /**
     * Premiere etape de la connexion.
     *
     * Une IP bloquee n'arrive pas jusqu'ici : le middleware `ip.blocked` l'a
     * renvoyee avec un 429 avant toute verification. Ce qui se joue ici, c'est
     * donc uniquement le sort d'une tentative recevable — identifiants faux,
     * connexion ouverte, ou connexion suspendue a un code.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('password') + $this->resolveLoginField($request);

        $resultat = $this->authentification->tenterConnexion($credentials, $request->ip());

        return match ($resultat['code']) {
            AuthenticationService::LOGIN_SUCCESS => response()->json([
                'success' => true,
                'code'    => AuthenticationService::LOGIN_SUCCESS,
                'data'    => $resultat['data'],
            ]),

            AuthenticationService::OTP_REQUIRED => response()->json([
                'success' => true,
                'code'    => AuthenticationService::OTP_REQUIRED,
                'message' => 'Un code de vérification a été envoyé.',
                'data'    => $resultat['data'],
            ]),

            AuthenticationService::ACCOUNT_DISABLED => response()->json([
                'success' => false,
                'code'    => AuthenticationService::ACCOUNT_DISABLED,
                'message' => 'Ce compte a été désactivé. Contactez l\'administration.',
            ], 403),

            default => response()->json([
                'success'       => false,
                'code'          => AuthenticationService::INVALID_CREDENTIALS,
                'message'       => 'Identifiants incorrects.',
                'attempts_left' => $resultat['data']['attempts_left'] ?? null,
            ], 401),
        };
    }

    // -------------------------------------------------------------------------
    // VERIFICATION DU CODE
    // -------------------------------------------------------------------------
    /**
     * Seconde etape : le code recu par e-mail contre les jetons de session.
     *
     * C'est le seul point d'entree qui delivre une session issue d'un challenge.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $challenge = $this->challenges->retrouver(
            $request->input('challenge_token'),
            $request->ip()
        );

        if (! $challenge) {
            // Jeton inconnu, deja consomme, ou presente depuis une autre IP :
            // les trois cas se repondent pareil, pour ne rien apprendre a qui
            // essaierait de deviner un jeton.
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_CHALLENGE',
                'message' => 'Session de vérification expirée. Reprenez la connexion.',
            ], 401);
        }

        $issue = $this->challenges->verifier($challenge, $request->input('otp'));

        if ($issue === LoginChallengeService::OTP_VERIFIED) {
            $donnees = $this->authentification->finaliser($challenge->fresh(), $request->ip());

            return response()->json([
                'success' => true,
                'code'    => LoginChallengeService::OTP_VERIFIED,
                'message' => 'Connexion réussie.',
                'data'    => $donnees,
            ]);
        }

        return match ($issue) {
            LoginChallengeService::OTP_EXPIRED => response()->json([
                'success' => false,
                'code'    => LoginChallengeService::OTP_EXPIRED,
                'message' => 'Le code a expiré.',
            ], 401),

            LoginChallengeService::OTP_TOO_MANY_ATTEMPTS => response()->json([
                'success' => false,
                'code'    => LoginChallengeService::OTP_TOO_MANY_ATTEMPTS,
                'message' => 'Trop de codes erronés. Reprenez la connexion depuis le début.',
            ], 429),

            default => response()->json([
                'success'       => false,
                'code'          => LoginChallengeService::INVALID_OTP,
                'message'       => 'Code incorrect.',
                'attempts_left' => max(
                    0,
                    (int) config('login_security.otp.max_attempts', 5) - $challenge->fresh()->attempts
                ),
            ], 401),
        };
    }

    // -------------------------------------------------------------------------
    // RENVOI DU CODE
    // -------------------------------------------------------------------------
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $challenge = $this->challenges->retrouver(
            $request->input('challenge_token'),
            $request->ip()
        );

        if (! $challenge) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_CHALLENGE',
                'message' => 'Session de vérification expirée. Reprenez la connexion.',
            ], 401);
        }

        $issue = $this->challenges->renvoyer($challenge);

        return match ($issue) {
            LoginChallengeService::OTP_SENT => response()->json([
                'success'    => true,
                'code'       => LoginChallengeService::OTP_SENT,
                'message'    => 'Un nouveau code vous a été envoyé.',
                'expires_in' => (int) config('login_security.otp.ttl', 300),
            ]),

            LoginChallengeService::OTP_EXPIRED => response()->json([
                'success' => false,
                'code'    => LoginChallengeService::OTP_EXPIRED,
                'message' => 'Le code a expiré. Reprenez la connexion.',
            ], 401),

            LoginChallengeService::OTP_RESEND_LIMIT => response()->json([
                'success' => false,
                'code'    => LoginChallengeService::OTP_RESEND_LIMIT,
                'message' => 'Nombre de renvois atteint. Reprenez la connexion.',
            ], 429),

            default => response()->json([
                'success'     => false,
                'code'        => LoginChallengeService::OTP_RESEND_COOLDOWN,
                'message'     => 'Veuillez patienter avant de demander un nouveau code.',
                'retry_after' => $this->delaiAvantRenvoi($challenge),
            ], 429),
        };
    }

    // -------------------------------------------------------------------------
    // MOT DE PASSE OUBLIE
    // -------------------------------------------------------------------------
    /**
     * Demande un lien de reinitialisation.
     *
     * La reponse est volontairement la meme que l'adresse existe ou non. Un
     * message different — « aucun compte avec cette adresse » — transformerait
     * ce formulaire ouvert en annuaire du personnel de l'etablissement.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $issue = $this->reinitialisation->envoyerLien(
            $request->input('email'),
            $request->ip()
        );

        if ($issue === PasswordResetService::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'code'    => 'RESET_THROTTLED',
                'message' => 'Un lien vient déjà de vous être envoyé. Patientez avant d\'en demander un autre.',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'code'    => 'RESET_LINK_SENT',
            'message' => 'Si un compte est associé à cette adresse, un lien de réinitialisation vient d\'être envoyé.',
        ]);
    }

    /**
     * Consomme le lien et enregistre le nouveau mot de passe.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $issue = $this->reinitialisation->reinitialiser(
            $request->input('email'),
            $request->input('token'),
            $request->input('password'),
            $request->ip()
        );

        return match ($issue) {
            PasswordResetService::PASSWORD_RESET => response()->json([
                'success' => true,
                'code'    => 'PASSWORD_RESET',
                'message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.',
            ]),

            PasswordResetService::RESET_TOKEN_EXPIRED => response()->json([
                'success' => false,
                'code'    => 'RESET_TOKEN_EXPIRED',
                'message' => 'Ce lien a expiré. Demandez-en un nouveau.',
            ], 422),

            default => response()->json([
                'success' => false,
                'code'    => 'RESET_TOKEN_INVALID',
                'message' => 'Ce lien n\'est plus valide. Demandez-en un nouveau.',
            ], 422),
        };
    }

    // -------------------------------------------------------------------------
    // DOUBLE AUTHENTIFICATION
    // -------------------------------------------------------------------------
    /**
     * Active ou coupe le second facteur du compte connecte.
     *
     * L'activer sans adresse e-mail se refuse : le code n'aurait aucun moyen
     * d'arriver, et l'utilisateur se fermerait sa propre porte.
     */
    public function toggleTwoFactor(TwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $demande = $request->boolean('enabled');

        if (! Hash::check($request->input('password'), $user->password)) {
            return ApiResponse::error('Mot de passe incorrect.', 401);
        }

        if ($demande && ! $user->email) {
            return ApiResponse::error(
                'Renseignez une adresse e-mail avant d\'activer la double authentification.',
                422
            );
        }

        $user->update(['two_factor_enabled' => $demande]);

        $this->journal->log(SecurityLogger::TWO_FACTOR_CHANGED, $user->id, [
            'enabled' => $demande,
        ]);

        return ApiResponse::success(
            ['two_factor_enabled' => $demande],
            $demande
                ? 'Double authentification activée.'
                : 'Double authentification désactivée.'
        );
    }

    // -------------------------------------------------------------------------
    // REFRESH
    // -------------------------------------------------------------------------
    public function refresh(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json(['message' => 'Token manquant.'], 401);
        }

        try {
            $payload = JWT::decode($bearerToken, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return response()->json(['message' => 'Token invalide ou expiré.'], 401);
        }

        if (($payload->token_type ?? '') !== 'refresh') {
            return response()->json(['message' => 'Ce token n\'est pas un refresh token.'], 401);
        }

        // Le refresh token ne contient pas de sub utilisateur => on attend l'id en body
        // $userId = $request->input('user_id');
        $userId = $payload->sub;
        $user   = User::findOrFail($userId);

        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        [$accessToken, $refreshToken, $expiresIn] = $this->generateTokenPair($user);

        return response()->json([
            'data' => [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => $expiresIn,
                'message'    => 'Bienvenue à nouveau '.$user->full_name,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------------------------
    public function logout(): JsonResponse
    {
        // Avec JWT pur, la révocation côté serveur se fait via une blacklist (cache/DB).
        // Implémentation minimale : on renvoie juste 200 et le client détruit ses tokens.
        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    // -------------------------------------------------------------------------
    // ME
    // -------------------------------------------------------------------------
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->profils->representer($user));
    }

    // -------------------------------------------------------------------------
    // MISE A JOUR DU PROFIL PERSONNEL
    // -------------------------------------------------------------------------
    /**
     * Coordonnees et photo du compte connecte.
     *
     * L'utilisateur ne designe pas la fiche a modifier : c'est la sienne, lue
     * sur le jeton. Aucun identifiant n'est accepte en entree, faute de quoi la
     * route deviendrait une porte vers le compte d'autrui.
     *
     * La reponse porte le profil complet — les memes champs que `/auth/me` —
     * pour que l'ecran se remette a jour sans second aller-retour.
     */
    public function updateProfil(UpdateProfilRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $this->profils->mettreAJour(
            $user,
            $request->donneesValidees(),
            $request->file('photo'),
            $request->boolean('supprimer_photo'),
        );

        return ApiResponse::success(
            $this->profils->representer($user),
            'Profil mis à jour avec succès.'
        );
    }

    // -------------------------------------------------------------------------
    // CHANGE PASSWORD (première connexion)
    // -------------------------------------------------------------------------
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->must_change_password) {
            return ApiResponse::error('Aucun changement de mot de passe requis.', 403);
        }

        $user->update([
            'password'             => bcrypt($request->input('password')),
            'must_change_password' => false,
        ]);

        return ApiResponse::success(null, 'Mot de passe mis à jour avec succès.');
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    /**
     * Génère un access token + refresh token pour un utilisateur.
     *
     * La fabrication vit desormais dans AuthenticationService, qui la partage
     * avec la connexion et la validation de code.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function generateTokenPair(User $user): array
    {
        return $this->authentification->genererPaireDeJetons($user);
    }

    /** Secondes restant avant qu'un nouveau code puisse etre demande. */
    private function delaiAvantRenvoi(\App\Models\LoginChallenge $challenge): int
    {
        $delai = (int) config('login_security.otp.resend_cooldown', 60);

        if (! $challenge->last_sent_at) {
            return 0;
        }

        $prochain = $challenge->last_sent_at->addSeconds($delai);

        return max(0, (int) ceil(now()->diffInSeconds($prochain, false)));
    }

    /**
     * Détermine le champ de login : email, username ou phone.
     */
    /**
     * Traduit l'identifiant fourni en critere de recherche.
     *
     * Le telephone vise « phone_one » et non « phone » : cette derniere
     * colonne n'existe pas, et Auth::attempt echouait sur une erreur SQL au
     * lieu de refuser proprement la connexion.
     *
     * Le numero est normalise avant comparaison : c'est l'identifiant des
     * familles, saisi tantot « 77 123 45 67 », tantot « +221771234567 ». Sans
     * cela, un tuteur enregistre sous une forme ne pourrait pas se connecter
     * sous l'autre. La normalisation est la meme qu'a l'enregistrement (voir
     * Tuteur::normaliserTelephone).
     */
    private function resolveLoginField(LoginRequest $request): array
    {
        if ($request->filled('email'))    return ['email'    => $request->email];
        if ($request->filled('username')) return ['username' => $request->username];

        return ['phone_one' => Tuteur::normaliserTelephone($request->phone)];
    }
}
