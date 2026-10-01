<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            NiveauSeeder::class,
            // Pas de MenuAdminSeeder : le parametrage a rejoint la page
            // Etablissement, l'admin voit ce menu via MenuManagerSeeder.
            MenuManagerSeeder::class,
            MenuSupervisorSeeder::class,
            MenuTreasurerSeeder::class,
            MenuTeacherSeeder::class,
            EtablissementSeeder::class,
            ParametrageSeeder::class,
            AnneeScolaireSeeder::class,
            // Les tarifs dependent de l'annee scolaire : la grille se pose
            // apres les annees, et avant toute inscription qui s'en sert.
            FraisScolaireSeeder::class,
            // Un decoupage trimestriel de depart, sur l'annee en cours. Il
            // reste modifiable depuis le module Periodes : l'etablissement qui
            // fonctionne en semestres remplace ces lignes par les siennes.
            // Pose apres les annees scolaires, dont il depend.
            PeriodeSeeder::class,
            // ClasseSeeder::class,
            // EleveSeeder::class,
        ]);
    }
}
