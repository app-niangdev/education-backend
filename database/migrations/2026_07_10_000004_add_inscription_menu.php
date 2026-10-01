<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Inscriptions » aux installations DEJA seedees.
 * Sur une base neuve, MenuManagerSeeder s'en charge (les migrations tournent
 * avant les seeders : roles et menus sont alors vides).
 */
return new class extends Migration
{
    private const CODE = 'inscriptions';

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
            $inscriptions = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Inscriptions',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/manager/inscriptions',
                'icon'        => 'assignment',
                'breadcrumbs' => true,
                'position'    => 6,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $inscriptions->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $inscriptions = Menu::where('code', self::CODE)->first();

        if (!$inscriptions) {
            return;
        }

        DB::transaction(function () use ($inscriptions) {
            MenuRole::where('menu_id', $inscriptions->id)->delete();
            $inscriptions->delete();
        });
    }
};
