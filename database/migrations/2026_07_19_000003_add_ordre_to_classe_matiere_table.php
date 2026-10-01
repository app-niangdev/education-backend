<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'ordre d'affichage des matières dans le programme d'une classe.
 *
 * Le bulletin papier suit un ordre pédagogique (Français, Mathématiques,
 * Sciences…) et non alphabétique. Cet ordre appartient à chaque classe : le
 * primaire et le lycée ne hiérarchisent pas leurs disciplines de la même
 * façon. Il est donc porté par `classe_matiere` et pas par `matieres`.
 *
 * Les programmes existants sont initialisés dans l'ordre alphabétique — celui
 * appliqué jusqu'ici — pour que rien ne change tant que personne n'a réordonné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classe_matiere', function (Blueprint $table) {
            $table->unsignedSmallInteger('ordre')->default(0)->after('volume_horaire');
        });

        // Numérotation par classe, alphabétique, à partir de 1.
        DB::statement("
            UPDATE classe_matiere cm
            SET ordre = numerotation.rang
            FROM (
                SELECT cm2.id,
                       ROW_NUMBER() OVER (PARTITION BY cm2.classe_id ORDER BY m.nom) AS rang
                FROM classe_matiere cm2
                JOIN matieres m ON m.id = cm2.matiere_id
            ) AS numerotation
            WHERE cm.id = numerotation.id
        ");
    }

    public function down(): void
    {
        Schema::table('classe_matiere', function (Blueprint $table) {
            $table->dropColumn('ordre');
        });
    }
};
