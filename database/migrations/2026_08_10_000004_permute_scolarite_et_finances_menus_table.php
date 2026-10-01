<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige l'ordre pose par reordonne_groupes_menus_table : Scolarite passe
 * avant Finances (et non l'inverse). Seules les dizaines de ces deux
 * groupes sont echangees, le reste (Paiement, Personnel, Mon
 * etablissement, liens directs) ne bouge pas :
 *
 *   Paiement(10-12) / Scolarite(20-23) / Finances(30-31) /
 *   Personnel(40-43) / Mon etablissement(50-54)
 *
 * Idempotente et sans effet sur une base neuve — Menu*Seeder porte
 * directement les bonnes valeurs.
 */
return new class extends Migration
{
    /** code => position */
    private const POSITIONS = [
        // --- Scolarité (20-23), avant Finances désormais --------------------
        'inscriptions'            => 20,
        'supervisor_inscriptions' => 20,
        'treasurer_inscriptions'  => 20,
        'assiduite'               => 21,
        'supervisor_assiduite'    => 21,
        'teacher_assiduite'       => 21,
        'bulletins'               => 22,
        'supervisor_bulletins'    => 22,
        'evaluations'             => 23,

        // --- Finances (30-31), après Scolarité désormais --------------------
        'manager_depenses'   => 30,
        'treasurer_depenses' => 30,
        'manager_bilan'      => 31,
        'treasurer_bilan'    => 31,
    ];

    /** Positions d'avant cette permutation, pour le rollback. */
    private const ANCIENNES_POSITIONS = [
        'inscriptions'            => 30,
        'supervisor_inscriptions' => 30,
        'treasurer_inscriptions'  => 30,
        'assiduite'               => 31,
        'supervisor_assiduite'    => 31,
        'teacher_assiduite'       => 31,
        'bulletins'               => 32,
        'supervisor_bulletins'    => 32,
        'evaluations'             => 33,
        'manager_depenses'        => 20,
        'treasurer_depenses'      => 20,
        'manager_bilan'           => 21,
        'treasurer_bilan'         => 21,
    ];

    public function up(): void
    {
        foreach (self::POSITIONS as $code => $position) {
            DB::table('menus')->where('code', $code)->update(['position' => $position]);
        }
    }

    public function down(): void
    {
        foreach (self::ANCIENNES_POSITIONS as $code => $position) {
            DB::table('menus')->where('code', $code)->update(['position' => $position]);
        }
    }
};
