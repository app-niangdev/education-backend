<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regroupement de la sidebar, pilote par le backend : chaque menu porte
 * desormais un `groupe` (nullable — null = lien direct, hors groupe), pour
 * que le front n'ait plus a deviner la structure a partir des codes.
 *
 * Le classement se base sur le SUFFIXE d'URL (school-infos, teachers,
 * bilan...), pas sur le prefixe de role (/index/manager/..., /index/
 * supervisor/...) : un meme suffixe range son menu dans le meme groupe quel
 * que soit le role proprietaire de la ligne, puisque chaque role possede sa
 * propre copie du menu (voir Menu*Seeder). C'est ce qui fait qu'« Eleves »
 * du surveillant et « Eleves » du manager, deux lignes distinctes, restent
 * toutes deux hors-groupe de la meme facon.
 *
 * La position suit le meme decoupage par dizaine pour toutes les lignes,
 * groupees ou non : 1-3 les liens directs epingles (accueil, eleves,
 * classes), 10-14 Mon etablissement, 20-23 Personnel, 30-34 Paiement,
 * 40-42 Scolarite, 90+ les liens directs restants (evaluations, messagerie),
 * 100 le journal d'activite, toujours en dernier. Une ligne absente pour un
 * role donne un trou dans la sequence, pas une rupture : le tri se fait
 * apres filtrage par role (voir AuthenticationService::ouvrirSession), donc
 * les positions manquantes n'affectent jamais la contiguite d'un groupe.
 *
 * Idempotente et sans effet sur une base neuve : une ligne dont le `code`
 * n'existe pas encore (seeders pas encore joues) n'est simplement pas
 * modifiee. Menu*Seeder porte desormais les memes valeurs, pour qu'une
 * installation fraiche parte directement dans le bon etat.
 */
return new class extends Migration
{
    /** code => [groupe, position] */
    private const GROUPES = [
        // --- Liens directs epingles en tete -----------------------------
        'default_manager'      => [null, 1],
        'default_supervisor'   => [null, 1],
        'default_treasurer'    => [null, 1],
        'default_teacher'      => [null, 1],
        'eleves'                => [null, 2],
        'supervisor_eleves'     => [null, 2],
        'treasurer_eleves'      => [null, 2],
        'classroom'             => [null, 3],
        'supervisor_classrooms' => [null, 3],

        // --- Mon établissement : school-infos, school-year, levels, periods, subjects
        'school'                 => ['Mon établissement', 10],
        'school_year'            => ['Mon établissement', 11],
        'level'                  => ['Mon établissement', 12],
        'periodes'               => ['Mon établissement', 13],
        'subjects'               => ['Mon établissement', 14],
        'supervisor_school_year' => ['Mon établissement', 11],
        'supervisor_levels'      => ['Mon établissement', 12],
        'supervisor_subjects'    => ['Mon établissement', 14],

        // --- Personnel : teachers, supervisors, treasurers, contracts
        'manager_teachers'    => ['Personnel', 20],
        'manager_supervisors' => ['Personnel', 21],
        'manager_treasurers'  => ['Personnel', 22],
        'manager_contrats'    => ['Personnel', 23],
        'supervisor_teachers' => ['Personnel', 20],

        // --- Paiement : encaissements, paiements, mensualites, expenses, bilan
        'treasurer_encaissements' => ['Paiement', 30],
        'treasurer_mensualites'   => ['Paiement', 31],
        'treasurer_paiements'     => ['Paiement', 32],
        'treasurer_depenses'      => ['Paiement', 33],
        'treasurer_bilan'         => ['Paiement', 34],
        'manager_depenses'        => ['Paiement', 33],
        'manager_bilan'           => ['Paiement', 34],

        // --- Scolarité : inscriptions, attendance, report-cards
        'inscriptions'            => ['Scolarité', 40],
        'assiduite'               => ['Scolarité', 41],
        'bulletins'                => ['Scolarité', 42],
        'supervisor_inscriptions'  => ['Scolarité', 40],
        'supervisor_assiduite'     => ['Scolarité', 41],
        'supervisor_bulletins'     => ['Scolarité', 42],
        'treasurer_inscriptions'   => ['Scolarité', 40],
        'teacher_assiduite'        => ['Scolarité', 41],

        // --- Liens directs restants, apres les groupes ------------------
        'evaluations' => [null, 90],
        'messagerie'  => [null, 91],

        // --- Toujours en dernier -----------------------------------------
        'activity_log' => [null, 100],
    ];

    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('groupe')->nullable()->after('title');
        });

        foreach (self::GROUPES as $code => [$groupe, $position]) {
            DB::table('menus')
                ->where('code', $code)
                ->update(['groupe' => $groupe, 'position' => $position]);
        }

        $this->retirerFraisScolairesResiduel();
    }

    /**
     * « Frais scolaires » n'existe plus comme ecran separe depuis que le
     * tarif se saisit avec le niveau (menu "level"/"supervisor_levels",
     * retitre "Niveaux & frais" par MenuManagerSeeder/MenuSupervisorSeeder).
     * Un residu d'avant cette fusion redeviendrait un doublon dans la
     * sidebar : on le retire s'il existe encore.
     *
     * Cible uniquement le code ou l'URL historiques de cet ecran — jamais le
     * titre, qui contient legitimement "frais" sur les menus fusionnes
     * ("Niveaux & frais") qu'il ne faut surtout pas supprimer.
     */
    private function retirerFraisScolairesResiduel(): void
    {
        $ids = DB::table('menus')
            ->whereIn('code', ['school-fees', 'school_fees', 'frais-scolaires', 'frais_scolaires'])
            ->orWhere('url', 'ilike', '%school-fees%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('menu_roles')->whereIn('menu_id', $ids)->delete();
        DB::table('menus')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('groupe');
        });

        // Les positions d'origine n'etaient pas homogenes (plusieurs menus
        // partageant la meme valeur, sans rapport avec les groupes) et ne
        // valent pas la peine d'etre reconstituees pour un rollback ; seule
        // la colonne est retiree.
    }
};
