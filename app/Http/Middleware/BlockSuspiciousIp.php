<?php

namespace App\Http\Middleware;

use App\Interfaces\LoginSecurityServiceInterface;
use App\Services\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La porte fermee aux adresses IP sous blocage.
 *
 * Place en tete de la pile API, avant l'authentification et avant tout
 * controleur : une IP bloquee doit repartir sans qu'on ait cherche son
 * utilisateur, verifie un mot de passe, touche a un compteur ou envoye quoi que
 * ce soit. C'est la seule maniere de garantir qu'un blocage ne se prolonge pas
 * tout seul et qu'il ne coute rien a encaisser.
 *
 * Un blocage encore en cours ne consomme donc aucune ecriture : la reponse se
 * limite a une lecture et a un 429.
 */
class BlockSuspiciousIp
{
    public function __construct(
        private readonly LoginSecurityServiceInterface $securite,
        private readonly SecurityLogger $journal,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->estExempte($request)) {
            return $next($request);
        }

        $etat = $this->securite->etat($request->ip());

        if ($etat === null || ! $etat->isBlocked()) {
            return $next($request);
        }

        $retryAfter = $etat->retryAfter();

        $this->journal->log(SecurityLogger::IP_BLOCKED_REQUEST, null, [
            'path'        => $request->path(),
            'method'      => $request->method(),
            'retry_after' => $retryAfter,
        ]);

        return response()->json([
            'success'       => false,
            'code'          => 'IP_BLOCKED',
            'message'       => 'Trop de tentatives de connexion. Veuillez patienter.',
            'blocked_until' => $etat->blocked_until->toIso8601String(),
            'retry_after'   => $retryAfter,
        ], 429)->withHeaders([
            // En-tete standard : les clients HTTP et proxies savent la lire.
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    /**
     * Routes servies malgre un blocage.
     *
     * Elles s'adressent a des tiers sans compte — celui qui scanne le QR code
     * d'un contrat imprime, le visiteur de la page d'accueil — et n'exposent
     * aucune donnee sensible ni aucune prise a une attaque par force brute. Les
     * fermer parce qu'un autre poste du meme reseau a rate sa connexion
     * penaliserait un innocent sans rien proteger.
     */
    private function estExempte(Request $request): bool
    {
        $motifs = (array) config('login_security.unblocked_paths', []);

        return $motifs !== [] && $request->is(...$motifs);
    }
}
