<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dans le groupe Paiement, Historique passe en dernier : c'est la
 * consultation de ce que les deux ecrans precedents (Encaissements,
 * Mensualites) ont saisi.
 *
 *   Encaissements(10) / Mensualites(11) / Historique(12)
 *
 * Idempotente et sans effet sur une base neuve — MenuTreasurerSeeder porte
 * directement les bonnes valeurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->where('code', 'treasurer_mensualites')->update(['position' => 11]);
        DB::table('menus')->where('code', 'treasurer_paiements')->update(['position' => 12]);
    }

    public function down(): void
    {
        DB::table('menus')->where('code', 'treasurer_paiements')->update(['position' => 11]);
        DB::table('menus')->where('code', 'treasurer_mensualites')->update(['position' => 12]);
    }
};
