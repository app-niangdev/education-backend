<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le barème par défaut d'une évaluation devient un paramètre de
 * l'établissement (modifiable), au lieu d'être codé en dur à 20.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametrages', function (Blueprint $table) {
            $table->unsignedTinyInteger('bareme_defaut')->default(20)->after('code_couleur');
        });

        // Valeur par défaut pour la ligne de paramétrage déjà en base.
        DB::table('parametrages')->whereNull('bareme_defaut')->update(['bareme_defaut' => 20]);
    }

    public function down(): void
    {
        Schema::table('parametrages', function (Blueprint $table) {
            $table->dropColumn('bareme_defaut');
        });
    }
};
