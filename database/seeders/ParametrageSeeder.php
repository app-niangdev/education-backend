<?php

namespace Database\Seeders;

use App\Models\Parametrage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ParametrageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Parametrage::create([
            'id' => 1,
            'telephone_transaction' => '779876510',
            'statut' => true,
            'en_maintenance' => false,
            // Hexadecimal 3 ou 6 caracteres uniquement : '#FFFF' etait invalide
            // et rejete cote front (chroma.valid), le theme ne s'appliquait pas.
            'code_couleur' => '#e5f811',
            'bareme_defaut' => 20,
        ]);
    }
}
