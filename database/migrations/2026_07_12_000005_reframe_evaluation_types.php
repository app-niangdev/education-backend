<?php

use App\Enums\TypeEvaluationEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recadre les types d'évaluation sur DEVOIR_1 / DEVOIR_2 / COMPOSITION
 * (cadre imposé par matière et par période) et retire le coefficient de la
 * table : il est désormais déduit de classe_matiere.coefficient. Le barème
 * par défaut vient de parametrages.bareme_defaut.
 *
 * La colonne `type` est un varchar + contrainte CHECK (enum Laravel sur
 * Postgres). On réécrit donc la contrainte et le défaut en SQL brut.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = array_column(TypeEvaluationEnum::cases(), 'value');
        $list   = "'" . implode("','", $values) . "'";

        // Aucune évaluation en base à ce stade ; par sécurité on réaligne
        // toute donnée résiduelle vers DEVOIR_1 avant de poser la contrainte.
        DB::statement("UPDATE evaluations SET type = 'DEVOIR_1' WHERE type NOT IN ($list)");

        DB::statement('ALTER TABLE evaluations ALTER COLUMN type DROP DEFAULT');
        DB::statement('ALTER TABLE evaluations DROP CONSTRAINT IF EXISTS evaluations_type_check');
        DB::statement("ALTER TABLE evaluations ADD CONSTRAINT evaluations_type_check CHECK (type::text = ANY (ARRAY[$list]::text[]))");
        DB::statement("ALTER TABLE evaluations ALTER COLUMN type SET DEFAULT 'DEVOIR_1'");

        // Le coefficient d'une évaluation est déduit de classe_matiere.
        if (Schema::hasColumn('evaluations', 'coefficient')) {
            DB::statement('ALTER TABLE evaluations DROP COLUMN coefficient');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE evaluations ADD COLUMN coefficient smallint NOT NULL DEFAULT 1');

        $old = "'DEVOIR','COMPOSITION','INTERROGATION','ORAL','TP'";
        DB::statement('ALTER TABLE evaluations ALTER COLUMN type DROP DEFAULT');
        DB::statement("UPDATE evaluations SET type = 'DEVOIR' WHERE type NOT IN ($old)");
        DB::statement('ALTER TABLE evaluations DROP CONSTRAINT IF EXISTS evaluations_type_check');
        DB::statement("ALTER TABLE evaluations ADD CONSTRAINT evaluations_type_check CHECK (type::text = ANY (ARRAY[$old]::text[]))");
        DB::statement("ALTER TABLE evaluations ALTER COLUMN type SET DEFAULT 'DEVOIR'");
    }
};
