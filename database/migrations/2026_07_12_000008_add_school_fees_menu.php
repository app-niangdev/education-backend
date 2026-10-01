<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Frais scolaires » aux installations DEJA seedees.
 * Sur une base neuve, MenuManagerSeeder s'en charge (les migrations tournent
 * avant les seeders : roles et menus sont alors vides).
 */
return new class extends Migration
{
    private const CODE = 'school-fees';

    public function up(): void
    {
        $roleIds = Role::whereIn('id', [
            RoleEnum::Manager->value,
            RoleEnum::Admin->value,
        ])->pluck('id');

        if ($roleIds->isEmpty() || Menu::where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function () use ($roleIds) {
            $menu = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Frais scolaires',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/manager/school-fees',
                'icon'        => 'receipt',
                'breadcrumbs' => true,
                'position'    => 4,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $menu->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $menu = Menu::where('code', self::CODE)->first();

        if (!$menu) {
            return;
        }

        DB::transaction(function () use ($menu) {
            MenuRole::where('menu_id', $menu->id)->delete();
            $menu->delete();
        });
    }
};
