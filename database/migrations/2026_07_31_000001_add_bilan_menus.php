<?php

use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Bilan » aux menus du tresorier et du manager.
 *
 * Les seeders de menu ne rejouent pas sur une base deja peuplee : sans cette
 * migration, la fonctionnalite existerait sans qu'aucun utilisateur puisse y
 * acceder. Le menu est une donnee applicative, pas un schema — d'ou une
 * migration de donnees.
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : roles et menus sont vides, on ne fait rien et c'est le seeder qui
 * cree les entrees.
 */
return new class extends Migration
{
    /** Les deux entrees a poser, chacune avec les roles qui la voient. */
    private const MENUS = [
        [
            'code'  => 'treasurer_bilan',
            'url'   => '/index/treasurer/bilan',
            'position' => 6,
            'roles' => [RoleEnum::Treasurer],
        ],
        [
            'code'  => 'manager_bilan',
            'url'   => '/index/manager/bilan',
            'position' => 12,
            'roles' => [RoleEnum::Manager, RoleEnum::Admin],
        ],
    ];

    public function up(): void
    {
        // Base neuve : sans role en table, l'insert dans menu_roles violerait la
        // contrainte de cle etrangere. Le seeder s'en chargera.
        $rolesExistants = DB::table('roles')->pluck('id')->all();

        if ($rolesExistants === []) {
            return;
        }

        foreach (self::MENUS as $definition) {
            // Un role prevu par la migration mais absent de la base ne doit pas
            // faire echouer l'ensemble : on ne pose que les liens tenables.
            $roles = array_filter(
                $definition['roles'],
                fn ($role) => in_array($role->value, $rolesExistants, true)
            );

            if ($roles === []) {
                continue;
            }

            // La migration doit pouvoir etre rejouee sans dupliquer l'entree.
            $menuId = DB::table('menus')->where('code', $definition['code'])->value('id');

            if ($menuId === null) {
                $menuId = DB::table('menus')->insertGetId([
                    'code'        => $definition['code'],
                    'title'       => 'Bilan',
                    'type'        => 'item',
                    'classes'     => 'nav-item',
                    'url'         => $definition['url'],
                    'icon'        => 'assessment',
                    'breadcrumbs' => true,
                    'position'    => $definition['position'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            foreach ($roles as $role) {
                $existe = DB::table('menu_roles')
                    ->where('menu_id', $menuId)
                    ->where('role_id', $role->value)
                    ->exists();

                if (! $existe) {
                    DB::table('menu_roles')->insert([
                        'menu_id'    => $menuId,
                        'role_id'    => $role->value,
                        'is_default' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $codes = array_column(self::MENUS, 'code');

        $ids = DB::table('menus')->whereIn('code', $codes)->pluck('id');

        DB::table('menu_roles')->whereIn('menu_id', $ids)->delete();
        DB::table('menus')->whereIn('id', $ids)->delete();
    }
};
