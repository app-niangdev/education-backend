<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les entrees « Dépenses » aux installations DEJA seedees. Sur une base
 * neuve, MenuManagerSeeder et MenuTreasurerSeeder s'en chargent.
 *
 * Deux entrees distinctes : le manager et l'admin passent par l'espace manager,
 * le tresorier par le sien — chaque espace ayant son propre prefixe de route.
 */
return new class extends Migration
{
    private const CODE_MANAGER   = 'manager_depenses';
    private const CODE_TREASURER = 'treasurer_depenses';

    public function up(): void
    {
        DB::transaction(function () {
            $this->creerMenu(
                code:    self::CODE_MANAGER,
                url:     '/index/manager/expenses',
                position: 11,
                roleIds: array_filter([
                    Role::find(RoleEnum::Manager->value)?->id,
                    Role::find(RoleEnum::Admin->value)?->id,
                ]),
            );

            $this->creerMenu(
                code:    self::CODE_TREASURER,
                url:     '/index/treasurer/expenses',
                position: 5,
                roleIds: array_filter([Role::find(RoleEnum::Treasurer->value)?->id]),
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $menus = Menu::whereIn('code', [self::CODE_MANAGER, self::CODE_TREASURER])->get();

            foreach ($menus as $menu) {
                MenuRole::where('menu_id', $menu->id)->delete();
                $menu->delete();
            }
        });
    }

    /** Idempotent : ni le menu ni ses rattachements ne sont dupliques. */
    private function creerMenu(string $code, string $url, int $position, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['code' => $code],
            [
                'title'       => 'Dépenses',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => $url,
                'icon'        => 'shopping_bag',
                'breadcrumbs' => true,
                'position'    => $position,
            ],
        );

        foreach ($roleIds as $roleId) {
            MenuRole::firstOrCreate(
                ['menu_id' => $menu->id, 'role_id' => $roleId],
                ['is_default' => false],
            );
        }
    }
};
