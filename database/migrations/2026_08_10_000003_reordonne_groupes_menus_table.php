<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reordonne les groupes entre eux (le `groupe` de chaque menu ne change pas,
 * seule sa `position` bouge) : Paiement, Finances, Scolarite, Personnel, Mon
 * etablissement — dans cet ordre, chacun sur sa propre dizaine. Les liens
 * directs pingles en tete (1-3) et ceux en fin de liste (messagerie,
 * journal d'activite) restent inchanges.
 *
 * Idempotente et sans effet sur une base neuve.
 */
return new class extends Migration
{
    /** code => position */
    private const POSITIONS = [
        // --- Paiement (10-12) ---------------------------------------------
        'treasurer_encaissements' => 10,
        'treasurer_paiements'     => 11,
        'treasurer_mensualites'   => 12,

        // --- Finances (20-21) ----------------------------------------------
        'manager_depenses'   => 20,
        'treasurer_depenses' => 20,
        'manager_bilan'      => 21,
        'treasurer_bilan'    => 21,

        // --- Scolarité (30-33) -----------------------------------------------
        'inscriptions'            => 30,
        'supervisor_inscriptions' => 30,
        'treasurer_inscriptions'  => 30,
        'assiduite'               => 31,
        'supervisor_assiduite'    => 31,
        'teacher_assiduite'       => 31,
        'bulletins'               => 32,
        'supervisor_bulletins'    => 32,
        'evaluations'             => 33,

        // --- Personnel (40-43) -----------------------------------------------
        'manager_teachers'    => 40,
        'supervisor_teachers' => 40,
        'manager_supervisors' => 41,
        'manager_treasurers'  => 42,
        'manager_contrats'    => 43,

        // --- Mon établissement (50-54) -----------------------------------
        'school'                 => 50,
        'school_year'            => 51,
        'supervisor_school_year' => 51,
        'level'                  => 52,
        'supervisor_levels'      => 52,
        'periodes'               => 53,
        'subjects'               => 54,
        'supervisor_subjects'    => 54,
    ];

    /** Positions d'avant ce reordonnancement, pour le rollback. */
    private const ANCIENNES_POSITIONS = [
        'treasurer_encaissements' => 30,
        'treasurer_paiements'     => 31,
        'treasurer_mensualites'   => 32,
        'manager_depenses'        => 35,
        'treasurer_depenses'      => 35,
        'manager_bilan'           => 36,
        'treasurer_bilan'         => 36,
        'inscriptions'            => 40,
        'supervisor_inscriptions' => 40,
        'treasurer_inscriptions'  => 40,
        'assiduite'               => 41,
        'supervisor_assiduite'    => 41,
        'teacher_assiduite'       => 41,
        'bulletins'               => 42,
        'supervisor_bulletins'    => 42,
        'evaluations'             => 43,
        'manager_teachers'        => 20,
        'supervisor_teachers'     => 20,
        'manager_supervisors'     => 21,
        'manager_treasurers'      => 22,
        'manager_contrats'        => 23,
        'school'                  => 10,
        'school_year'             => 11,
        'supervisor_school_year'  => 11,
        'level'                   => 12,
        'supervisor_levels'       => 12,
        'periodes'                => 13,
        'subjects'                => 14,
        'supervisor_subjects'     => 14,
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
