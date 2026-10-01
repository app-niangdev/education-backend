<?php

namespace App\Interfaces;

use App\Models\LoginChallenge;
use App\Models\User;

/**
 * Les authentifications suspendues a leur second facteur.
 */
interface LoginChallengeServiceInterface
{
    /**
     * Ouvre un challenge pour un utilisateur dont le mot de passe est verifie,
     * genere le code et l'envoie.
     *
     * Les challenges encore ouverts du meme utilisateur sont invalides : un
     * seul code doit valoir a un instant donne.
     *
     * @param string $raison TWO_FACTOR ou IP_PREVIOUSLY_BLOCKED
     */
    public function ouvrir(User $user, string $ip, string $raison): LoginChallenge;

    /**
     * Le challenge designe par son jeton, s'il est encore utilisable et
     * provient de la meme IP. Null sinon.
     */
    public function retrouver(string $token, string $ip): ?LoginChallenge;

    /**
     * Confronte un code au challenge.
     *
     * @return string L'une des issues : OTP_VERIFIED, INVALID_OTP,
     *                OTP_EXPIRED, OTP_TOO_MANY_ATTEMPTS.
     */
    public function verifier(LoginChallenge $challenge, string $code): string;

    /**
     * Regenere et renvoie un code pour un challenge en cours.
     *
     * @return string OTP_SENT, OTP_EXPIRED, OTP_RESEND_LIMIT ou OTP_RESEND_COOLDOWN.
     */
    public function renvoyer(LoginChallenge $challenge): string;

    /** Supprime les challenges consommes ou perimes depuis assez longtemps. */
    public function purger(int $jours): int;
}
