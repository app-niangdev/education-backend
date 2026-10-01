<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Affine le decoupage pose par add_groupe_to_menus_table :
 *
 *  - « Paiement » se resserre sur l'encaissement lui-meme (encaissements,
 *    paiements, mensualites) ;
 *  - « Finances » devient un groupe a part pour les depenses et le bilan —
 *    l'un est sortant, l'autre une synthese, ni l'un ni l'autre n'est un
 *    encaissement, les regrouper sous « Paiement » les y noyait ;
 *  - « Évaluations » rejoint Scolarite (avec inscriptions, assiduite,
 *    bulletins) au lieu de rester hors groupe.
 *
 * Renomme aussi l'ecran d'historique des paiements (url .../paiements) en
 * « Historique » : dans le groupe « Paiement », le nommer « Paiements »
 * dupliquait le nom du groupe et pretait a confusion.
 *
 * Idempotente (UPDATE par valeur exacte) et sans effet sur une base neuve —
 * Menu*Seeder porte directement les bonnes valeurs.
 */
return new class extends Migration
{
    /** code => [groupe, position] */
    private const GROUPES = [
        // --- Paiement : encaissements, paiements, mensualites ------------
        'treasurer_encaissements' => ['Paiement', 30],
        'treasurer_paiements'     => ['Paiement', 31],
        'treasurer_mensualites'   => ['Paiement', 32],

        // --- Finances : expenses, bilan -----------------------------------
        'treasurer_depenses' => ['Finances', 35],
        'treasurer_bilan'    => ['Finances', 36],
        'manager_depenses'   => ['Finances', 35],
        'manager_bilan'      => ['Finances', 36],

        // --- Scolarité : evaluations rejoint le groupe --------------------
        'evaluations' => ['Scolarité', 43],
    ];

    public function up(): void
    {
        foreach (self::GROUPES as $code => [$groupe, $position]) {
            DB::table('menus')
                ->where('code', $code)
                ->update(['groupe' => $groupe, 'position' => $position]);
        }

        DB::table('menus')
            ->where('code', 'treasurer_paiements')
            ->update(['title' => 'Historique']);
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('code', ['treasurer_depenses', 'treasurer_bilan', 'manager_depenses', 'manager_bilan'])
            ->update(['groupe' => 'Paiement']);

        DB::table('menus')
            ->where('code', 'evaluations')
            ->update(['groupe' => null, 'position' => 90]);

        DB::table('menus')
            ->where('code', 'treasurer_paiements')
            ->update(['title' => 'Paiements']);

        // Les positions 30-34 d'origine (encaissements/mensualites/paiements/
        // depenses/bilan tous sous « Paiement ») ne sont pas reconstituees :
        // sans interet pour un rollback, la colonne groupe suffit a annuler
        // l'effet visible.
    }
};
