<?php

namespace Database\Seeders;

use App\Enums\StatutAnneeScolaire;
use App\Models\AnneeScolaire;
use Illuminate\Database\Seeder;

/**
 * Deux années scolaires :
 *  - 2025-2026 : clôturée (octobre 2025 → juin 2026).
 *  - 2026-2027 : en cours  (octobre 2026 → juin 2027).
 *
 * Idempotent (updateOrCreate sur le nom) et garantit qu'une seule année porte
 * en_cours = true.
 */
class AnneeScolaireSeeder extends Seeder
{
    public function run(): void
    {
        $enCours = AnneeScolaire::updateOrCreate(
            ['nom' => '2026-2027'],
            [
                'date_debut' => '2026-10-01',
                'date_fin'   => '2027-06-30',
                'en_cours'   => true,
                'statut'     => StatutAnneeScolaire::ENCOURS,
            ],
        );

        AnneeScolaire::updateOrCreate(
            ['nom' => '2025-2026'],
            [
                'date_debut' => '2025-10-01',
                'date_fin'   => '2026-06-30',
                'en_cours'   => false,
                'statut'     => StatutAnneeScolaire::CLOTURER,
            ],
        );

        // Une seule année en cours : on retire le drapeau aux autres.
        AnneeScolaire::where('id', '!=', $enCours->id)
            ->where('en_cours', true)
            ->update(['en_cours' => false]);

        $this->command?->info('AnneeScolaireSeeder : 2025-2026 (clôturée) et 2026-2027 (en cours) prêtes.');
    }
}
