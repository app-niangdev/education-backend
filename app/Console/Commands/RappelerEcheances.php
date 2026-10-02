<?php

namespace App\Console\Commands;

use App\Services\RelancePaiementService;
use Illuminate\Console\Command;

/**
 * Rappelle aux familles, sur WhatsApp, les mensualites qui arrivent a
 * echeance. Lancee chaque matin par le planificateur (routes/console.php) :
 * prevenir avant le 5 evite une partie des retards qu'il faudrait relancer
 * ensuite.
 */
class RappelerEcheances extends Command
{
    protected $signature = 'relances:rappel-echeances';

    protected $description = 'Envoie aux tuteurs le rappel WhatsApp des mensualités bientôt échues';

    public function handle(RelancePaiementService $service): int
    {
        $bilan = $service->rappelerEcheances();

        if ($bilan['motif'] !== null) {
            $this->warn($bilan['motif']);
        }

        $this->info(sprintf(
            '%d rappel(s) envoyé(s), %d ignoré(s), %d en échec.',
            $bilan['envoyes'],
            $bilan['ignores'],
            $bilan['echecs'],
        ));

        return self::SUCCESS;
    }
}
