<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Redirige le menu « Tableau de bord » du trésorier vers son écran de
 * statistiques dédié (/index/treasurer/home) plutôt que la racine générique.
 */
return new class extends Migration
{
    private const CODE = 'default_treasurer';

    public function up(): void
    {
        Menu::where('code', self::CODE)->update(['url' => '/index/treasurer/home']);
    }

    public function down(): void
    {
        Menu::where('code', self::CODE)->update(['url' => '/index']);
    }
};
