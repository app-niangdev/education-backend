<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dans le groupe Scolarite, Evaluations passe avant Bulletins : le
 * bulletin est la synthese des evaluations saisies, il se lit donc apres.
 *
 *   Inscriptions(20) / Assiduite(21) / Evaluations(22) / Bulletins(23)
 *
 * Idempotente et sans effet sur une base neuve — Menu*Seeder porte
 * directement les bonnes valeurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->where('code', 'evaluations')->update(['position' => 22]);
        DB::table('menus')->whereIn('code', ['bulletins', 'supervisor_bulletins'])->update(['position' => 23]);
    }

    public function down(): void
    {
        DB::table('menus')->where('code', 'evaluations')->update(['position' => 23]);
        DB::table('menus')->whereIn('code', ['bulletins', 'supervisor_bulletins'])->update(['position' => 22]);
    }
};
