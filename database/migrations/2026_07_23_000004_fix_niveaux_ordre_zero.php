<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repare les niveaux restes a `ordre = 0`.
 *
 * La colonne `ordre` avait un defaut a 0. Comme le formulaire de creation ne
 * l'envoyait pas, tout niveau cree depuis l'interface tombait a 0 — rendant
 * deux niveaux « premiers » de leur cycle, et faisant echouer la contrainte
 * unique (cycle, ordre) des le second. Le service attribue desormais l'ordre
 * automatiquement ; cette migration remet d'aplomb les donnees deja saisies.
 *
 * Chaque niveau a 0 est place a la suite du dernier ordre non nul de son cycle,
 * departage par nom pour rester deterministe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE niveaux n
            SET ordre = numerotation.rang
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY cycle ORDER BY nom)
                       + COALESCE(
                           (SELECT MAX(ordre) FROM niveaux m
                            WHERE m.cycle = niveaux.cycle AND m.ordre > 0 AND m.deleted_at IS NULL),
                           0
                         ) AS rang
                FROM niveaux
                WHERE ordre = 0 AND deleted_at IS NULL
            ) AS numerotation
            WHERE n.id = numerotation.id
        ");
    }

    public function down(): void
    {
        // Irreversible : on ne sait pas quels niveaux valaient 0 avant, et y
        // revenir recreerait le conflit que cette migration corrige.
    }
};
