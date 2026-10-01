<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Seeder;

class MenuTeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teacherId = RoleEnum::Teacher->value;

        $dashboard = Menu::create([
            'code'        => 'default_teacher',
            'title'       => 'Tableau de bord',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/teacher/home',
            'icon'        => 'dashboard',
            'breadcrumbs' => true,
            'position'    => 1,
        ]);

        // Le menu « Évaluations » est créé par MenuManagerSeeder (exécuté avant
        // ce seeder). On le rattache simplement au rôle enseignant.
        $evaluations = Menu::where('code', 'evaluations')->first();

        // L'enseignant fait l'appel : son point d'entrée est la journée de
        // cours, pas le registre d'assiduité du surveillant. Meme groupe
        // Scolarite que les ecrans d'assiduite du manager et du surveillant
        // (voir la migration add_groupe_to_menus_table).
        $appel = Menu::create([
            'code'        => 'teacher_assiduite',
            'title'       => 'Appel',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/teacher/attendance',
            'icon'        => 'assignment_turned_in',
            'breadcrumbs' => true,
            'position'    => 21,
        ]);

        MenuRole::insert([
            ['menu_id' => $dashboard->id, 'role_id' => $teacherId, 'is_default' => true],
            ['menu_id' => $appel->id,     'role_id' => $teacherId, 'is_default' => false],
        ]);

        if ($evaluations) {
            MenuRole::firstOrCreate([
                'menu_id' => $evaluations->id,
                'role_id' => $teacherId,
            ], ['is_default' => false]);
        }
    }
}
