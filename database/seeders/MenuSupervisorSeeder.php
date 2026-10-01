<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Seeder;

/**
 * Menus du surveillant. Perimetre volontairement restreint a la vie scolaire,
 * sans aucun acces financier :
 *  - Inscriptions : il peut CREER une inscription mais jamais l'encaisser
 *    (l'encaissement reste reserve au tresorier via finance-tresorier).
 *  - Classes : consultation, liste des eleves d'une classe, export PDF/Excel,
 *    et emploi du temps (consultation + telechargement).
 *  - Eleves : consultation + creation / modification.
 *  - Lecture seule : matieres, frais scolaires, niveaux, enseignants (et leurs
 *    matieres associees) et annees scolaires.
 *
 * Les URLs pointent vers l'espace front dedie /index/supervisor/... (garde par
 * le SupervisorGuard cote Angular).
 */
class MenuSupervisorSeeder extends Seeder
{
    public function run(): void
    {
        $supervisorId = RoleEnum::Supervisor->value;

        // La position suit le decoupage par dizaine de la sidebar regroupee
        // (voir les migrations add_groupe_to_menus_table,
        // scinde_paiement_et_finances_menus_table,
        // reordonne_groupes_menus_table et
        // permute_scolarite_et_finances_menus_table) : 1-3 les liens directs
        // epingles, puis les groupes dans l'ordre Scolarite(20-22) /
        // Personnel(40) / Mon etablissement(51-54).
        $dashboard = Menu::create([
            'code'        => 'default_supervisor',
            'title'       => 'Tableau de bord',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/home',
            'icon'        => 'dashboard',
            'breadcrumbs' => true,
            'position'    => 1,
        ]);

        $inscriptions = Menu::create([
            'code'        => 'supervisor_inscriptions',
            'title'       => 'Inscriptions',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/inscriptions',
            'icon'        => 'assignment',
            'breadcrumbs' => true,
            'position'    => 20,
        ]);

        $eleves = Menu::create([
            'code'        => 'supervisor_eleves',
            'title'       => 'Élèves',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/students',
            'icon'        => 'contacts',
            'breadcrumbs' => true,
            'position'    => 2,
        ]);

        $classrooms = Menu::create([
            'code'        => 'supervisor_classrooms',
            'title'       => 'Classes',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/classrooms',
            'icon'        => 'school',
            'breadcrumbs' => true,
            'position'    => 3,
        ]);

        // Niveaux et frais scolaires ne font qu'une entree : le tarif se
        // saisit avec le niveau, sur un seul ecran.
        $levels = Menu::create([
            'code'        => 'supervisor_levels',
            'title'       => 'Niveaux & frais',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/levels',
            'icon'        => 'grade',
            'breadcrumbs' => true,
            'position'    => 52,
        ]);

        $subjects = Menu::create([
            'code'        => 'supervisor_subjects',
            'title'       => 'Matières',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/subjects',
            'icon'        => 'book',
            'breadcrumbs' => true,
            'position'    => 54,
        ]);

        $teachers = Menu::create([
            'code'        => 'supervisor_teachers',
            'title'       => 'Enseignants',
            'groupe'      => 'Personnel',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/teachers',
            'icon'        => 'school',
            'breadcrumbs' => true,
            'position'    => 40,
        ]);

        $schoolYear = Menu::create([
            'code'        => 'supervisor_school_year',
            'title'       => 'Années scolaires',
            'groupe'      => 'Mon établissement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/school-year',
            'icon'        => 'date_range',
            'breadcrumbs' => true,
            'position'    => 51,
        ]);

        // Le cœur du métier du surveillant : justifier, corriger et suivre
        // l'assiduité. L'appel lui-même est fait par les enseignants.
        $assiduite = Menu::create([
            'code'        => 'supervisor_assiduite',
            'title'       => 'Assiduité',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/attendance',
            'icon'        => 'assignment_turned_in',
            'breadcrumbs' => true,
            'position'    => 21,
        ]);

        // Consultation seule : la génération et la publication restent au
        // manager et à l'admin.
        $bulletins = Menu::create([
            'code'        => 'supervisor_bulletins',
            'title'       => 'Bulletins',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/supervisor/report-cards',
            'icon'        => 'assignment',
            'breadcrumbs' => true,
            'position'    => 23,
        ]);

        MenuRole::insert([
            ['menu_id' => $dashboard->id,    'role_id' => $supervisorId, 'is_default' => true],
            ['menu_id' => $assiduite->id,    'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $bulletins->id,    'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $inscriptions->id, 'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $eleves->id,       'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $classrooms->id,   'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $levels->id,       'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $subjects->id,     'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $teachers->id,     'role_id' => $supervisorId, 'is_default' => false],
            ['menu_id' => $schoolYear->id,   'role_id' => $supervisorId, 'is_default' => false],
        ]);
    }
}
