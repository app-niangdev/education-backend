<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire l'entree « Parametrages » sur les installations DEJA seedees : le
 * parametrage a rejoint la page Etablissement, qui porte desormais l'identite,
 * les contacts, le logo et les reglages de l'application.
 *
 * L'admin garde acces a tout : il voit deja le menu « Etablissement » (attache
 * a son role par MenuManagerSeeder), et la section parametrage n'est rendue que
 * pour lui. Le manager, lui, ne la voit pas — le serveur refuserait sa mise a
 * jour en 403.
 *
 * La route Angular /index/admin/settings redirige vers la page fusionnee, les
 * liens deja en circulation restent donc valides.
 */
return new class extends Migration
{
    private const CODE = 'settings';

    public function up(): void
    {
        DB::transaction(function () {
            $menu = Menu::where('code', self::CODE)->first();

            if (! $menu) {
                return;
            }

            MenuRole::where('menu_id', $menu->id)->delete();
            $menu->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            if (Menu::where('code', self::CODE)->exists()) {
                return;
            }

            $menu = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Parametrages',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/admin/settings',
                'icon'        => 'settings',
                'breadcrumbs' => true,
                'position'    => 3,
            ]);

            // Le parametrage n'a jamais ete ouvert qu'a l'admin.
            MenuRole::firstOrCreate([
                'menu_id' => $menu->id,
                'role_id' => RoleEnum::Admin->value,
            ], ['is_default' => false]);
        });
    }
};
