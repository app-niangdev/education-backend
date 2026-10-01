<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les menus « Inscriptions » et « Paiements » du tresorier aux
 * installations DEJA seedees : MenuTreasurerSeeder utilise Menu::create(), le
 * rejouer dupliquerait le tableau de bord.
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : roles et menus sont vides, on ne fait rien et c'est le seeder qui
 * cree les entrees.
 */
return new class extends Migration
{
    /** code => [title, url, icon, position] */
    private const MENUS = [
        'treasurer_encaissements' => ['Inscriptions', '/index/treasurer/encaissements', 'receipt', 2],
        'treasurer_paiements'     => ['Paiements', '/index/treasurer/paiements', 'assignment_turned_in', 3],
    ];

    public function up(): void
    {
        $treasurerId = Role::where('id', RoleEnum::Treasurer->value)->value('id');

        // Base neuve : sans role tresorier, l'insert dans menu_roles violerait
        // la contrainte de cle etrangere. Le seeder s'en chargera.
        if ($treasurerId === null) {
            return;
        }

        DB::transaction(function () use ($treasurerId) {
            foreach (self::MENUS as $code => [$title, $url, $icon, $position]) {
                if (Menu::where('code', $code)->exists()) {
                    continue;
                }

                $menu = Menu::create([
                    'code'        => $code,
                    'title'       => $title,
                    'type'        => 'item',
                    'classes'     => 'nav-item',
                    'url'         => $url,
                    'icon'        => $icon,
                    'breadcrumbs' => true,
                    'position'    => $position,
                ]);

                MenuRole::firstOrCreate([
                    'menu_id' => $menu->id,
                    'role_id' => $treasurerId,
                ], ['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        $menus = Menu::whereIn('code', array_keys(self::MENUS))->get();

        DB::transaction(function () use ($menus) {
            foreach ($menus as $menu) {
                MenuRole::where('menu_id', $menu->id)->delete();
                $menu->delete();
            }
        });
    }
};
