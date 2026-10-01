<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Database\Seeder;

/**
 * Une classe de démonstration par niveau, rattachée à l'année en cours. Sert
 * de socle à EleveSeeder (qui inscrit ses élèves dans la première classe).
 *
 * Idempotent : updateOrCreate sur (code, annee_scolaire_id).
 */
class ClasseSeeder extends Seeder
{
    private const EFFECTIF_MAX = 40;

    public function run(): void
    {
        $annee = AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderBy('date_debut')->first();

        if (!$annee) {
            $this->command?->warn('ClasseSeeder : aucune année scolaire, rien à faire.');
            return;
        }

        $niveaux = Niveau::orderBy('id')->get();

        if ($niveaux->isEmpty()) {
            $this->command?->warn('ClasseSeeder : aucun niveau, lancez NiveauSeeder d\'abord.');
            return;
        }

        $count = 0;

        foreach ($niveaux as $niveau) {
            // Ex. « 6EME » → nom « 6ème A », code « 6EME-A ».
            $code = "{$niveau->code}-A";

            Classe::updateOrCreate(
                ['code' => $code, 'annee_scolaire_id' => $annee->id],
                [
                    'nom'          => "{$niveau->nom} A",
                    'effectif_max' => self::EFFECTIF_MAX,
                    'niveau_id'    => $niveau->id,
                ],
            );

            $count++;
        }

        $this->command?->info(
            "ClasseSeeder : {$count} classe(s) créée(s) pour « {$annee->nom} »."
        );
    }
}
