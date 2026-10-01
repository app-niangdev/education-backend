<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Eleves » aux installations DEJA seedees : MenuManagerSeeder
 * utilise Menu::create(), le rejouer dupliquerait tous les menus.
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : roles et menus sont vides, on ne fait rien et c'est
 * MenuManagerSeeder qui cree l'entree.
 */
return new class extends Migration
{
    private const CODE = 'eleves';

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
            $eleves = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Élèves',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/manager/students',
                'icon'        => 'contacts',
                'breadcrumbs' => true,
                'position'    => 5,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $eleves->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $eleves = Menu::where('code', self::CODE)->first();

        if (!$eleves) {
            return;
        }

        DB::transaction(function () use ($eleves) {
            MenuRole::where('menu_id', $eleves->id)->delete();
            $eleves->delete();
        });
    }
};
