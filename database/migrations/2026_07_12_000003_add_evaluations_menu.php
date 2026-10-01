<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entrée de menu « Évaluations » aux installations DEJA seedees.
 * Sur une base neuve (migrate:fresh), les seeders (MenuTeacherSeeder /
 * MenuManagerSeeder) créent l'entrée : ici on ne fait rien tant que les rôles
 * n'existent pas. Même logique que add_classroom_menu / add_subjects_menu.
 *
 * L'enseignant est le public principal (saisie des notes) ; le manager et
 * l'admin y ont accès pour la supervision.
 */
return new class extends Migration
{
    private const CODE = 'evaluations';

    public function up(): void
    {
        $roleIds = Role::whereIn('id', [
            RoleEnum::Teacher->value,
            RoleEnum::Manager->value,
            RoleEnum::Admin->value,
        ])->pluck('id');

        if ($roleIds->isEmpty() || Menu::where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function () use ($roleIds) {
            $evaluations = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Évaluations',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/teacher/evaluations',
                'icon'        => 'grading',
                'breadcrumbs' => true,
                'position'    => 5,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $evaluations->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $evaluations = Menu::where('code', self::CODE)->first();

        if (!$evaluations) {
            return;
        }

        DB::transaction(function () use ($evaluations) {
            MenuRole::where('menu_id', $evaluations->id)->delete();
            $evaluations->delete();
        });
    }
};
