<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unicité du code de niveau, restreinte aux niveaux actifs.
     *
     * Le code n'avait aucune contrainte en base : seule la règle de validation
     * `unique:niveaux,code` la portait, et elle comptait les lignes supprimées.
     * Un niveau supprimé bloquait donc définitivement la réutilisation de son
     * code, alors même qu'une suppression n'est autorisée que si le niveau
     * n'a ni classe ni grille tarifaire (cf. NiveauRepository::isUsed).
     *
     * L'index partiel `WHERE deleted_at IS NULL` reprend la convention déjà
     * retenue pour niveaux_cycle_ordre_unique : la contrainte s'applique aux
     * seules lignes vivantes, et la validation ne peut plus être contournée
     * par deux créations concurrentes.
     */
    public function up(): void
    {
        // Deux niveaux actifs partageant déjà un code empêcheraient la création
        // de l'index. On les désambiguïse en suffixant les doublons les plus
        // récents, plutôt que d'échouer la migration.
        DB::statement("
            UPDATE niveaux AS n
            SET code = n.code || '-' || n.id
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY code ORDER BY id) AS rang
                FROM niveaux
                WHERE deleted_at IS NULL
            ) AS doublons
            WHERE n.id = doublons.id AND doublons.rang > 1
        ");

        DB::statement(
            'CREATE UNIQUE INDEX niveaux_code_unique
             ON niveaux (code)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS niveaux_code_unique');
    }
};
