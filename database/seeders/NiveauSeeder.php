<?php

namespace Database\Seeders;

use App\Enums\CycleNiveauEnum;
use App\Models\Niveau;
use Illuminate\Database\Seeder;

/**
 * Le referentiel des niveaux.
 *
 * `ordre` situe chaque niveau DANS SON CYCLE : le college et le lycee ont
 * chacun leur propre numerotation a partir de 1. C'est ce qui permet au
 * passage de classe de determiner le niveau suivant, et de s'arreter en fin
 * de cycle ou l'orientation releve d'une decision humaine.
 *
 * Ce jeu est volontairement partiel (il manque la 4e, la 3e et la 1re) :
 * c'est un jeu de demonstration, pas le referentiel reel d'un etablissement.
 * Les ordres laissent la place aux niveaux manquants.
 *
 * Les tarifs ne figurent pas ici : ils dependent de l'annee scolaire et sont
 * poses par le FraisScolaireSeeder.
 */
class NiveauSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'nom' => 'Sixième',
                'code' => '6EME',
                'ordre' => 1,
                'cycle' => CycleNiveauEnum::COLLEGE->value,
            ],
            [
                'nom' => 'Cinquième',
                'code' => '5EME',
                'ordre' => 2,
                'cycle' => CycleNiveauEnum::COLLEGE->value,
            ],
            [
                'nom' => 'Seconde',
                'code' => '2NDE',
                'ordre' => 1,
                'cycle' => CycleNiveauEnum::LYCEE->value,
            ],
            [
                'nom' => 'Terminale',
                'code' => 'TLE',
                'ordre' => 3,
                'cycle' => CycleNiveauEnum::LYCEE->value,
            ],
        ];

        foreach ($levels as $level) {
            Niveau::updateOrCreate(
                ['code' => $level['code']],
                $level
            );
        }
    }
}
