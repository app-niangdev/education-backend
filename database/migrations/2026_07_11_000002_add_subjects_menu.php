<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree de menu « Matières » aux installations DEJA seedees.
 * Sur une base neuve, c'est MenuManagerSeeder qui la cree (voir la logique
 * identique dans add_classroom_menu).
 */
return new class extends Migration
{
    private const CODE = 'subjects';

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
            $subjects = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Matières',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/manager/subjects',
                'icon'        => 'book',
                'breadcrumbs' => true,
                'position'    => 4,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $subjects->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $subjects = Menu::where('code', self::CODE)->first();

        if (!$subjects) {
            return;
        }

        DB::transaction(function () use ($subjects) {
            MenuRole::where('menu_id', $subjects->id)->delete();
            $subjects->delete();
        });
    }
};
