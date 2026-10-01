<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Mensualités » du tresorier aux installations DEJA seedees.
 * Sur une base neuve, MenuTreasurerSeeder s'en charge.
 */
return new class extends Migration
{
    private const CODE = 'treasurer_mensualites';

    public function up(): void
    {
        $treasurer = Role::find(RoleEnum::Treasurer->value);

        if (!$treasurer || Menu::where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function () use ($treasurer) {
            $menu = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Mensualités',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/treasurer/mensualites',
                'icon'        => 'calendar_today',
                'breadcrumbs' => true,
                'position'    => 3,
            ]);

            MenuRole::firstOrCreate([
                'menu_id' => $menu->id,
                'role_id' => $treasurer->id,
            ], ['is_default' => false]);
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
