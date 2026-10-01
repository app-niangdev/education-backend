<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les entrées de menu « Assiduité » aux installations DEJA seedees.
 * Sur une base neuve (migrate:fresh), ce sont les seeders qui les créent :
 * ici les rôles n'existent pas encore et la migration ne fait rien.
 *
 * Trois entrées distinctes, car les trois rôles n'ouvrent pas le même écran :
 *   - l'enseignant arrive sur SES cours du jour, pour faire l'appel ;
 *   - le surveillant et le manager arrivent sur le registre, pour justifier
 *     et suivre.
 * Le trésorier n'y a aucun accès.
 */
return new class extends Migration
{
    private const CODE_MANAGER    = 'assiduite';
    private const CODE_SUPERVISOR = 'supervisor_assiduite';
    private const CODE_TEACHER    = 'teacher_assiduite';

    public function up(): void
    {
        $this->creerMenu(
            code:     self::CODE_MANAGER,
            titre:    'Assiduité',
            url:      '/index/manager/attendance',
            position: 10,
            roleIds:  Role::whereIn('id', [
                RoleEnum::Manager->value,
                RoleEnum::Admin->value,
            ])->pluck('id'),
        );

        $this->creerMenu(
            code:     self::CODE_SUPERVISOR,
            titre:    'Assiduité',
            url:      '/index/supervisor/attendance',
            position: 11,
            roleIds:  Role::whereIn('id', [RoleEnum::Supervisor->value])->pluck('id'),
        );

        // L'enseignant fait l'appel : son point d'entrée est la journée de
        // cours, pas le registre.
        $this->creerMenu(
            code:     self::CODE_TEACHER,
            titre:    'Appel',
            url:      '/index/teacher/attendance',
            position: 6,
            roleIds:  Role::whereIn('id', [RoleEnum::Teacher->value])->pluck('id'),
        );
    }

    public function down(): void
    {
        foreach ([self::CODE_MANAGER, self::CODE_SUPERVISOR, self::CODE_TEACHER] as $code) {
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

    private function creerMenu(
        string $code,
        string $titre,
        string $url,
        int $position,
        $roleIds,
    ): void {
        if ($roleIds->isEmpty() || Menu::where('code', $code)->exists()) {
            return;
        }

        DB::transaction(function () use ($code, $titre, $url, $position, $roleIds) {
            $menu = Menu::create([
                'code'        => $code,
                'title'       => $titre,
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => $url,
                'icon'        => 'assignment_turned_in',
                'breadcrumbs' => true,
                'position'    => $position,
            ]);

            foreach ($roleIds as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $menu->id,
                    'role_id' => $roleId,
                ], ['is_default' => false]);
            }
        });
    }
};
