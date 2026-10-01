<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deplace la saisie des inscriptions du manager vers le tresorier.
 *
 * L'inscription ouvre les frais et l'echeancier : c'est un acte de caisse. Le
 * tresorier la saisit desormais au guichet, dans la foulee de la fiche eleve —
 * l'ecran enchaine de l'une a l'autre, ce qui garantit qu'aucun eleve ne reste
 * cree sans inscription.
 *
 * Le manager garde la liste en consultation (annulation, suppression, PDF) :
 * son menu « Inscriptions » est conserve, seul le bouton de creation disparait
 * cote frontend. On ajoute ici les deux entrees dont le tresorier a besoin :
 * « Eleves » et « Inscriptions ».
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : sans role tresorier, l'insert dans menu_roles violerait la cle
 * etrangere. On ne fait alors rien, MenuTreasurerSeeder s'en charge.
 */
return new class extends Migration
{
    /**
     * Les entrees se glissent avant les ecrans d'encaissement : on saisit
     * l'eleve et son inscription avant de l'encaisser.
     *
     * code => [title, url, icon, position]
     */
    private const MENUS = [
        'treasurer_eleves'        => ['Élèves', '/index/treasurer/students', 'contacts', 2],
        // « assignment » plutot que « how_to_reg » : le frontend n'embarque
        // qu'un jeu restreint d'icones, et une icone absente part en 404.
        'treasurer_inscriptions'  => ['Inscriptions', '/index/treasurer/inscriptions', 'assignment', 3],
    ];

    /**
     * Les ecrans financiers reculent d'autant : « Inscriptions » (encaissement)
     * est renomme en « Encaissements » pour le distinguer du nouveau menu de
     * saisie, avec lequel le libelle entrerait en collision.
     *
     * code => [title, position]
     */
    private const DECALAGES = [
        'treasurer_encaissements' => ['Encaissements', 4],
        'treasurer_mensualites'   => ['Mensualités', 5],
        'treasurer_paiements'     => ['Paiements', 6],
        'treasurer_depenses'      => ['Dépenses', 7],
        'treasurer_bilan'         => ['Bilan', 8],
    ];

    public function up(): void
    {
        $treasurerId = Role::where('id', RoleEnum::Treasurer->value)->value('id');

        if ($treasurerId === null) {
            return;
        }

        DB::transaction(function () use ($treasurerId) {
            foreach (self::DECALAGES as $code => [$title, $position]) {
                Menu::where('code', $code)->update([
                    'title'    => $title,
                    'position' => $position,
                ]);
            }

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
        DB::transaction(function () {
            $menus = Menu::whereIn('code', array_keys(self::MENUS))->get();

            foreach ($menus as $menu) {
                MenuRole::where('menu_id', $menu->id)->delete();
                $menu->delete();
            }

            // Retablit les libelles et positions d'avant le decalage.
            $avant = [
                'treasurer_encaissements' => ['Inscriptions', 2],
                'treasurer_mensualites'   => ['Mensualités', 3],
                'treasurer_paiements'     => ['Paiements', 4],
                'treasurer_depenses'      => ['Dépenses', 5],
                'treasurer_bilan'         => ['Bilan', 6],
            ];

            foreach ($avant as $code => [$title, $position]) {
                Menu::where('code', $code)->update([
                    'title'    => $title,
                    'position' => $position,
                ]);
            }
        });
    }
};
