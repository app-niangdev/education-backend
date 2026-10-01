<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use Illuminate\Database\Seeder;

/**
 * La grille tarifaire de demonstration : un bareme par (annee scolaire,
 * niveau). C'est desormais l'unique source des montants d'inscription et de
 * mensualite — le niveau n'en porte plus.
 *
 * Idempotent : l'unicite (annee, niveau) evite tout doublon au rejeu, et un
 * bareme deja saisi n'est jamais ecrase.
 */
class FraisScolaireSeeder extends Seeder
{
    /** Tarifs par code de niveau : [inscription, mensualite]. */
    private const TARIFS = [
        '6EME' => [25000, 10000],
        '5EME' => [25000, 10000],
        '2NDE' => [30000, 12000],
        'TLE'  => [35000, 15000],
    ];

    public function run(): void
    {
        $annees = AnneeScolaire::all();

        if ($annees->isEmpty()) {
            $this->command?->warn('FraisScolaireSeeder : aucune année scolaire, rien à faire.');
            return;
        }

        $niveaux = Niveau::all()->keyBy('code');
        $crees   = 0;

        foreach ($annees as $annee) {
            foreach (self::TARIFS as $code => [$inscription, $mensualite]) {
                $niveau = $niveaux->get($code);

                if (!$niveau) {
                    continue;
                }

                $nombreMensualites = FraisScolaire::NOMBRE_MENSUALITES_DEFAUT;

                $frais = FraisScolaire::firstOrCreate(
                    ['annee_scolaire_id' => $annee->id, 'niveau_id' => $niveau->id],
                    [
                        'montant_inscription' => $inscription,
                        'montant_mensualite'  => $mensualite,
                        'nombre_mensualites'  => $nombreMensualites,
                        // Calcule explicitement : DatabaseSeeder desactive les
                        // evenements de modele (WithoutModelEvents), donc le hook
                        // saving() qui derive normalement frais_annuel ne se
                        // declenche pas pendant le seeding.
                        'frais_annuel'        => $inscription + $mensualite * $nombreMensualites,
                    ],
                );

                if ($frais->wasRecentlyCreated) {
                    $crees++;
                }
            }
        }

        $this->command?->info("FraisScolaireSeeder : {$crees} barème(s) créé(s).");
    }
}
