<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Le journal des evenements d'authentification.
 *
 * Ecrit sur le canal `security`, separe du journal applicatif : ces lignes se
 * relisent apres coup, lors d'une enquete, et n'ont pas a se perdre au milieu
 * des traces de requetes.
 *
 * Ce qui n'entre jamais ici : mot de passe, code OTP en clair, jeton d'acces ou
 * de rafraichissement. Un journal n'est pas un endroit sur, et un journal qui
 * contient de quoi se connecter est une seconde base de mots de passe.
 */
class SecurityLogger
{
    public const LOGIN_FAILED             = 'LOGIN_FAILED';
    public const IP_BLOCKED               = 'IP_BLOCKED';
    public const IP_BLOCK_EXPIRED         = 'IP_BLOCK_EXPIRED';
    public const IP_BLOCKED_REQUEST       = 'IP_BLOCKED_REQUEST';
    public const LOGIN_SUCCESS            = 'LOGIN_SUCCESS';
    public const OTP_SENT                 = 'OTP_SENT';
    public const OTP_VERIFICATION_FAILED  = 'OTP_VERIFICATION_FAILED';
    public const OTP_VERIFIED             = 'OTP_VERIFIED';
    public const LOGIN_COMPLETED          = 'LOGIN_COMPLETED';
    public const TWO_FACTOR_CHANGED       = 'TWO_FACTOR_CHANGED';
    public const PASSWORD_RESET_REQUESTED = 'PASSWORD_RESET_REQUESTED';
    public const PASSWORD_RESET_SENT      = 'PASSWORD_RESET_SENT';
    public const PASSWORD_RESET_FAILED    = 'PASSWORD_RESET_FAILED';
    public const PASSWORD_RESET_COMPLETED = 'PASSWORD_RESET_COMPLETED';

    /**
     * Un utilisateur a change ses propres coordonnees. Le telephone servant
     * d'identifiant de connexion, sa modification appartient au journal de
     * securite et non au simple suivi d'activite.
     */
    public const PROFIL_UPDATED           = 'PROFIL_UPDATED';

    /**
     * Un lien d'ouverture de compte a ete emis. Ce lien vaut acces : qui le
     * detient choisit le mot de passe. Sa trace importe autant que celle d'une
     * reinitialisation.
     */
    public const ACTIVATION_LINK_SENT     = 'ACTIVATION_LINK_SENT';

    public function __construct(private readonly Request $request) {}

    /**
     * @param array<string, mixed> $contexte Details de l'evenement. Ne doit
     *                                       contenir aucun secret.
     */
    public function log(string $evenement, ?int $userId = null, array $contexte = []): void
    {
        Log::channel('security')->info($evenement, array_merge([
            'event'      => $evenement,
            'ip_address' => $this->request->ip(),
            'user_id'    => $userId,
            'user_agent' => $this->request->userAgent(),
            'timestamp'  => now()->toIso8601String(),
        ], $contexte));
    }
}
