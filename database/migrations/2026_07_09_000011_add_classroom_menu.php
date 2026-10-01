<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MenuManagerSeeder utilise Menu::create() : le rejouer dupliquerait tous les
 * menus deja en base. Cette migration ajoute la seule entree « Classes » aux
 * installations DEJA seedees.
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : roles et menus sont vides. On ne fait alors rien, c'est
 * MenuManagerSeeder qui cree l'entree.
 */
return new class extends Migration
{
    private const CODE = 'classroom';

    public function up(): void
    {
        $roleIds = Role::whereIn('id', [
            RoleEnum::Manager->value,
            RoleEnum::Admin->value,
        ])->pluck('id');

        // Base neuve : les seeders n'ont pas encore tourne, le menu sera cree
        // par MenuManagerSeeder. Sans roles, l'insert dans menu_roles violerait
        // la contrainte de cle etrangere.
        if ($roleIds->isEmpty() || Menu::where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function () use ($roleIds) {
            $classrooms = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Classes',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/manager/classrooms',
                'icon'        => 'school',
                'breadcrumbs' => true,
                'position'    => 4,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $classrooms->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $classrooms = Menu::where('code', self::CODE)->first();

        if (!$classrooms) {
            return;
        }

        DB::transaction(function () use ($classrooms) {
            MenuRole::where('menu_id', $classrooms->id)->delete();
            $classrooms->delete();
        });
    }
};
