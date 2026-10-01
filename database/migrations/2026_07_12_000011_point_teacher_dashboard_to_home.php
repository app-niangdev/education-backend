<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Redirige le menu « Tableau de bord » de l'enseignant vers son écran de
 * statistiques dédié (/index/teacher/home) plutôt que la racine générique.
 */
return new class extends Migration
{
    private const CODE = 'default_teacher';

    public function up(): void
    {
        Menu::where('code', self::CODE)->update(['url' => '/index/teacher/home']);
    }

    public function down(): void
    {
        Menu::where('code', self::CODE)->update(['url' => '/index']);
    }
};
