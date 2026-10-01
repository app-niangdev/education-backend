<?php

namespace App\Console\Commands;

use App\Interfaces\LoginChallengeServiceInterface;
use App\Interfaces\LoginSecurityServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use Illuminate\Console\Command;

/**
 * Menage des donnees de securite du login.
 *
 * Les etats d'IP inactifs et les challenges consommes n'ont plus d'utilite
 * passe un delai : les garder ferait de ces tables un journal de trafic qui
 * croit sans fin, et prolongerait inutilement la conservation d'adresses IP.
 *
 * Les blocages en cours sont preserves : les effacer reviendrait a deverrouiller
 * une IP avant terme.
 */
class PruneLoginSecurity extends Command
{
    protected $signature = 'login-security:prune';

    protected $description = 'Supprime les etats de securite dormants et les challenges OTP perimes';

    public function handle(
        LoginSecurityServiceInterface $securite,
        LoginChallengeServiceInterface $challenges,
        PasswordResetServiceInterface $reinitialisation,
    ): int {
        $joursSecurite   = (int) config('login_security.prune.security_days', 30);
        $joursChallenges = (int) config('login_security.prune.challenges_days', 7);

        $etats  = $securite->purger($joursSecurite);
        $codes  = $challenges->purger($joursChallenges);
        // Un jeton perime n'ouvre plus rien, mais le garder laisserait tourner
        // une table d'adresses e-mail sans usage.
        $liens  = $reinitialisation->purger();

        $this->info("États de sécurité supprimés : {$etats}");
        $this->info("Challenges OTP supprimés : {$codes}");
        $this->info("Liens de réinitialisation expirés supprimés : {$liens}");

        return self::SUCCESS;
    }
}
