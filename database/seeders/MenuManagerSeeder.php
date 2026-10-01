<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Seeder;

class MenuManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managerId = RoleEnum::Manager->value;
        $adminId = RoleEnum::Admin->value;

        // La position suit le decoupage par dizaine de la sidebar regroupee
        // (voir les migrations add_groupe_to_menus_table,
        // scinde_paiement_et_finances_menus_table,
        // reordonne_groupes_menus_table, permute_scolarite_et_finances_menus_table
        // et permute_evaluations_et_bulletins_menus_table) : 1-3 les liens
        // directs epingles, puis les groupes dans l'ordre Paiement(10-12) /
        // Scolarite(20-23, avec Evaluations avant Bulletins) / Finances(30-31)
        // / Personnel(40-43) / Mon etablissement(50-54), 90+ les liens directs
        // restants, 100 toujours en dernier.
        $dashboard = Menu::create([
            'code'        => 'default_manager',
            'title'       => 'Tableau de bord',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/home',
            'icon'        => 'dashboard',
            'breadcrumbs' => true,
            'position'    => 1,
        ]);

        $infoEtablissement = Menu::create([
            'code'        => 'school',
            'title'       => 'Etablissement',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/school-infos',
            'icon'        => 'school',
            'breadcrumbs' => true,
            'position'    => 50,
        ]);

        $SchoolYear = Menu::create([
            'code'        => 'school_year',
            'title'       => 'Années Scolaire',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/school-year',
            'icon'        => 'date_range',
            'breadcrumbs' => true,
            'position'    => 51,
        ]);

        $periodes = Menu::create([
            'code'        => 'periodes',
            'title'       => 'Périodes',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/periods',
            'icon'        => 'event_note',
            'breadcrumbs' => true,
            'position'    => 53,
        ]);

        // Niveaux et frais scolaires ne font qu'une entree : le tarif se
        // saisit avec le niveau, sur un seul ecran.
        $levels = Menu::create([
            'code'        => 'level',
            'title'       => 'Niveaux & frais',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/levels',
            'icon'        => 'grade',
            'breadcrumbs' => true,
            'position'    => 52,
        ]);

        $classrooms = Menu::create([
            'code'        => 'classroom',
            'title'       => 'Classes',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/classrooms',
            'icon'        => 'school',
            'breadcrumbs' => true,
            'position'    => 3,
        ]);

        $subjects = Menu::create([
            'code'        => 'subjects',
            'title'       => 'Matières',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/subjects',
            'icon'        => 'book',
            'breadcrumbs' => true,
            'position'    => 54,
        ]);

        $eleves = Menu::create([
            'code'        => 'eleves',
            'title'       => 'Élèves',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/students',
            'icon'        => 'contacts',
            'breadcrumbs' => true,
            'position'    => 2,
        ]);

        $inscriptions = Menu::create([
            'code'        => 'inscriptions',
            'title'       => 'Inscriptions',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/inscriptions',
            'icon'        => 'assignment',
            'breadcrumbs' => true,
            'position'    => 20,
        ]);

        // Toujours en dernier : c'est le registre de tout ce que les autres
        // ecrans ont fait, il n'a pas sa place au milieu d'un groupe.
        $activityLog = Menu::create([
            'code'        => 'activity_log',
            'title'       => 'Journal d\'activité',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/activity-log',
            'icon'        => 'bookmarks',
            'breadcrumbs' => true,
            'position'    => 100,
        ]);

        $teachers = Menu::create([
            'code'        => 'manager_teachers',
            'title'       => 'Enseignants',
            'groupe'      => 'Personnel',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/teachers',
            'icon'        => 'school',
            'breadcrumbs' => true,
            'position'    => 40,
        ]);

        $supervisors = Menu::create([
            'code'        => 'manager_supervisors',
            'title'       => 'Surveillants',
            'groupe'      => 'Personnel',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/supervisors',
            'icon'        => 'verified_user',
            'breadcrumbs' => true,
            'position'    => 41,
        ]);

        $treasurers = Menu::create([
            'code'        => 'manager_treasurers',
            'title'       => 'Trésoriers',
            'groupe'      => 'Personnel',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/treasurers',
            'icon'        => 'receipt',
            'breadcrumbs' => true,
            'position'    => 42,
        ]);

        // Groupee sous Scolarite, apres inscriptions/assiduite, avant
        // bulletins : le bulletin est la synthese des evaluations saisies,
        // il se lit donc apres.
        $evaluations = Menu::create([
            'code'        => 'evaluations',
            'title'       => 'Évaluations',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/teacher/evaluations',
            'icon'        => 'grading',
            'breadcrumbs' => true,
            'position'    => 22,
        ]);

        $bulletins = Menu::create([
            'code'        => 'bulletins',
            'title'       => 'Bulletins',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/report-cards',
            'icon'        => 'assignment',
            'breadcrumbs' => true,
            'position'    => 23,
        ]);

        $assiduite = Menu::create([
            'code'        => 'assiduite',
            'title'       => 'Assiduité',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/attendance',
            'icon'        => 'assignment_turned_in',
            'breadcrumbs' => true,
            'position'    => 21,
        ]);

        // Depenses et bilan forment leur propre groupe « Finances » : ni l'un
        // ni l'autre n'est un encaissement (l'un est sortant, l'autre une
        // synthese), les regrouper sous « Paiement » les y noyait.
        $depenses = Menu::create([
            'code'        => 'manager_depenses',
            'title'       => 'Dépenses',
            'groupe'      => 'Finances',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/expenses',
            'icon'        => 'shopping_bag',
            'breadcrumbs' => true,
            'position'    => 30,
        ]);

        // Le bilan agrege ce que les depenses et les encaissements ont saisi :
        // il se place juste apres les depenses, dont il est la lecture.
        $bilan = Menu::create([
            'code'        => 'manager_bilan',
            'title'       => 'Bilan',
            'groupe'      => 'Finances',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/bilan',
            'icon'        => 'assessment',
            'breadcrumbs' => true,
            'position'    => 31,
        ]);

        // Les contrats couvrent les trois profils du personnel : ils forment
        // une entree a part, apres les fiches individuelles.
        $contrats = Menu::create([
            'code'        => 'manager_contrats',
            'title'       => 'Contrats',
            'groupe'      => 'Personnel',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/manager/contracts',
            'icon'        => 'description',
            'breadcrumbs' => true,
            'position'    => 43,
        ]);

        MenuRole::insert([
            ['menu_id' => $dashboard->id,         'role_id' => $managerId, 'is_default' => true],
            ['menu_id' => $evaluations->id,       'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $bulletins->id,         'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $assiduite->id,         'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $infoEtablissement->id, 'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $SchoolYear->id,        'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $periodes->id,          'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $levels->id,            'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $classrooms->id,        'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $subjects->id,          'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $eleves->id,            'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $inscriptions->id,      'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $activityLog->id,       'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $teachers->id,          'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $supervisors->id,       'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $treasurers->id,        'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $depenses->id,          'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $bilan->id,             'role_id' => $managerId, 'is_default' => false],
            ['menu_id' => $contrats->id,          'role_id' => $managerId, 'is_default' => false],

            // Admin
            ['menu_id' => $dashboard->id,         'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $SchoolYear->id,        'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $activityLog->id,       'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $periodes->id,          'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $levels->id,            'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $classrooms->id,        'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $subjects->id,          'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $eleves->id,            'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $inscriptions->id,      'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $infoEtablissement->id, 'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $teachers->id,          'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $supervisors->id,       'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $treasurers->id,        'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $evaluations->id,       'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $bulletins->id,         'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $assiduite->id,         'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $depenses->id,          'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $bilan->id,             'role_id' => $adminId, 'is_default' => false],
            ['menu_id' => $contrats->id,          'role_id' => $adminId, 'is_default' => false],
        ]);
    }
}
