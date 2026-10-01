<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Contrats » aux installations DEJA seedees. Sur une base
 * neuve, MenuManagerSeeder s'en charge.
 *
 * Reservee a l'admin et au manager : un contrat porte la remuneration, que ni
 * le surveillant ni le tresorier n'ont a consulter pour leurs collegues — le
 * controleur applique la meme restriction.
 */
return new class extends Migration
{
    private const CODE = 'manager_contrats';

    public function up(): void
    {
        DB::transaction(function () {
            $roleIds = array_filter([
                Role::find(RoleEnum::Manager->value)?->id,
                Role::find(RoleEnum::Admin->value)?->id,
            ]);

            if ($roleIds === []) {
                return;
            }

            // Idempotent : ni le menu ni ses rattachements ne sont dupliques.
            $menu = Menu::firstOrCreate(
                ['code' => self::CODE],
                [
                    'title'       => 'Contrats',
                    'type'        => 'item',
                    'classes'     => 'nav-item',
                    'url'         => '/index/manager/contracts',
                    'icon'        => 'description',
                    'breadcrumbs' => true,
                    'position'    => 12,
                ],
            );

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate(
                    ['menu_id' => $menu->id, 'role_id' => $roleId],
                    ['is_default' => false],
                );
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $menu = Menu::where('code', self::CODE)->first();

            if (!$menu) {
                return;
            }

            MenuRole::where('menu_id', $menu->id)->delete();
            $menu->delete();
        });
    }
};
