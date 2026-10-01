<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un bareme n'a pas besoin d'historique, et le soft delete entrait en
     * conflit avec la contrainte unique (annee_scolaire_id, niveau_id) : une
     * ligne soft-supprimee occupe toujours la combinaison en base, alors que
     * l'application la considere comme absente (referentiels, generation de
     * grille). Resultat : le niveau libere n'apparaissait jamais comme "sans
     * bareme", et le retarifer plantait sur un doublon fantome.
     *
     * On passe donc en hard delete : la suppression d'un bareme libere
     * reellement la cle unique.
     */
    public function up(): void
    {
        // Les baremes deja soft-supprimes n'ont plus de raison de survivre :
        // les garder les ressusciterait silencieusement une fois la colonne
        // deleted_at retiree (plus rien pour les distinguer des actifs).
        DB::table('frais_scolaires')->whereNotNull('deleted_at')->delete();

        Schema::table('frais_scolaires', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('frais_scolaires', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
