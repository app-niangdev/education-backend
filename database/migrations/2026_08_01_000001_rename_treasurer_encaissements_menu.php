<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Renomme le menu tresorier « Encaissements » en « Inscriptions ».
 *
 * L'ecran ne sert qu'a encaisser les inscriptions ; le libelle nomme desormais
 * ce qui est encaisse, comme le menu « Mensualites » voisin. Seul le titre
 * change : le code et l'url restent identiques, les routes front ne bougent pas.
 */
return new class extends Migration
{
    private const CODE = 'treasurer_encaissements';

    public function up(): void
    {
        Menu::where('code', self::CODE)->update(['title' => 'Inscriptions']);
    }

    public function down(): void
    {
        Menu::where('code', self::CODE)->update(['title' => 'Encaissements']);
    }
};
