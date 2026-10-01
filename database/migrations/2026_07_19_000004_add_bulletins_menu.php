<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entrée de menu « Bulletins » aux installations DEJA seedees.
 * Sur une base neuve (migrate:fresh), ce sont les seeders qui la créent :
 * ici les rôles n'existent pas encore et la migration ne fait rien.
 * Même logique que add_evaluations_menu.
 *
 * Le manager et l'admin génèrent et publient ; le surveillant consulte. Le
 * trésorier n'y a pas accès, et l'enseignant passe par son propre espace.
 */
return new class extends Migration
{
    private const CODE            = 'bulletins';
    private const CODE_SUPERVISOR = 'supervisor_bulletins';

    public function up(): void
    {
        // Le surveillant a ses propres entrées de menu, pointant vers son
        // espace : deux menus distincts, comme le font déjà les seeders.
        $this->creerMenu(
            code:    self::CODE,
            url:     '/index/manager/report-cards',
            position: 9,
            roleIds: Role::whereIn('id', [
                RoleEnum::Manager->value,
                RoleEnum::Admin->value,
            ])->pluck('id'),
        );

        $this->creerMenu(
            code:    self::CODE_SUPERVISOR,
            url:     '/index/supervisor/report-cards',
            position: 10,
            roleIds: Role::whereIn('id', [RoleEnum::Supervisor->value])->pluck('id'),
        );
    }

    public function down(): void
    {
        foreach ([self::CODE, self::CODE_SUPERVISOR] as $code) {
            $menu = Menu::where('code', $code)->first();

            if (!$menu) {
                continue;
            }

            DB::transaction(function () use ($menu) {
                MenuRole::where('menu_id', $menu->id)->delete();
                $menu->delete();
            });
        }
    }

    private function creerMenu(string $code, string $url, int $position, $roleIds): void
    {
        if ($roleIds->isEmpty() || Menu::where('code', $code)->exists()) {
            return;
        }

        DB::transaction(function () use ($code, $url, $position, $roleIds) {
            $bulletins = Menu::create([
                'code'        => $code,
                'title'       => 'Bulletins',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => $url,
                'icon'        => 'assignment',
                'breadcrumbs' => true,
                'position'    => $position,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $bulletins->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }
};
