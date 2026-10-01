<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use Illuminate\Database\Seeder;

class EtablissementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Etablissement::create([
            'id'                             => 1,
            'nom'                              => 'ACADEMIE DE FORMATION DES INSTITUTEURS',
            'nom_court'                        => 'AFI ACADEMIE',
            'slogan'                           => 'L\'excellence au service de l\'innovation',
            'adresse'                          => 'Dakar, Sénégal',
            'email'                            => 'afiformation2704@gmail.com',
            'telephone_principal'              => '774701145',
            'telephone_secondaire'             => '771227752',
            'site_web'                         => '',
            'lien_facebook'                    => '',
            'lien_instagram'                   => '',
            'inspection_academique'            => 'Inspection d’Académie de Dakar',
            'inspection_education_formation'   => 'Inspection de l’Éducation et de la Formation de Grand Dakar',
        ]);
    }
}
