<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'ordre d'un niveau dans son cycle.
 *
 * Rien n'indiquait jusqu'ici qu'après la Sixième vient la Cinquième : le
 * passage de classe n'avait aucune progression sur laquelle s'appuyer.
 *
 * L'ordre est RELATIF AU CYCLE — Sixième est le premier niveau du collège,
 * Seconde le premier du lycée. Le niveau suivant est donc celui d'ordre + 1
 * dans le même cycle ; en fin de cycle (Troisième vers Seconde), aucun
 * successeur n'est trouvé et l'établissement arbitre à la main. C'est
 * volontaire : les passerelles entre cycles relèvent d'une orientation, pas
 * d'un automatisme.
 */
return new class extends Migration
{
    /** Ordre de départ des niveaux existants, par code. */
    private const ORDRE_INITIAL = [
        '6EME' => 1,
        '5EME' => 2,
        '4EME' => 3,
        '3EME' => 4,
        '2NDE' => 1,
        '1ERE' => 2,
        'TLE'  => 3,
    ];

    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->unsignedSmallInteger('ordre')->default(0)->after('code');
            $table->index(['cycle', 'ordre']);
        });

        foreach (self::ORDRE_INITIAL as $code => $ordre) {
            DB::table('niveaux')->where('code', $code)->update(['ordre' => $ordre]);
        }

        // Les niveaux hors référentiel connu prennent un ordre alphabétique
        // dans leur cycle, pour qu'aucun ne reste à 0 (ce qui les rendrait
        // tous « premiers » et fausserait la recherche du suivant).
        DB::statement("
            UPDATE niveaux n
            SET ordre = numerotation.rang
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY cycle ORDER BY nom)
                       + COALESCE((SELECT MAX(ordre) FROM niveaux m WHERE m.cycle = niveaux.cycle), 0) AS rang
                FROM niveaux
                WHERE ordre = 0 AND deleted_at IS NULL
            ) AS numerotation
            WHERE n.id = numerotation.id
        ");

        // Deux niveaux ne peuvent pas se disputer la même place : sans cette
        // contrainte, « le niveau suivant » ne serait pas déterministe.
        DB::statement(
            'CREATE UNIQUE INDEX niveaux_cycle_ordre_unique
             ON niveaux (cycle, ordre)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS niveaux_cycle_ordre_unique');

        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropIndex(['cycle', 'ordre']);
            $table->dropColumn('ordre');
        });
    }
};
