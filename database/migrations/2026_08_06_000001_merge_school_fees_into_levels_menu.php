<?php

use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fusionne « Frais scolaires » dans « Niveaux » sur les installations DEJA
 * seedees : les deux modules ne font plus qu'un ecran, le tarif se saisissant
 * avec le niveau. Sur une base neuve, MenuManagerSeeder ne cree deja plus que
 * l'entree unique.
 *
 * L'entree school-fees est retiree du menu ; la route Angular du meme nom
 * redirige vers `levels`, les liens deja en circulation restent donc valides.
 */
return new class extends Migration
{
    /**
     * Manager/admin et surveillant ont chacun leur jeu de menus, aux codes
     * distincts mais pointant sur les memes deux ecrans : les deux fusionnent.
     *
     * @var array<string, string> code du menu frais => code du menu niveaux
     */
    private const FUSIONS = [
        'school-fees'            => 'level',
        'supervisor_school_fees' => 'supervisor_levels',
    ];

    private const TITRE_FUSIONNE = 'Niveaux & frais';
    private const TITRE_ORIGINE  = 'Niveaux';

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::FUSIONS as $codeFrais => $codeNiveaux) {
                $frais = Menu::where('code', $codeFrais)->first();

                if ($frais) {
                    MenuRole::where('menu_id', $frais->id)->delete();
                    $frais->delete();
                }

                // Le libelle dit ce que l'ecran contient desormais : les deux.
                Menu::where('code', $codeNiveaux)
                    ->update(['title' => self::TITRE_FUSIONNE]);
            }
        });
    }

    /**
     * Restaure l'entree « Frais scolaires » de chaque jeu de menus, ouverte aux
     * memes roles que « Niveaux » : les deux modules ont toujours vise le meme
     * public. L'url et la position d'origine sont celles des seeders.
     *
     * @var array<string, array{url: string, position: int}>
     */
    private const RESTAURATION = [
        'school-fees'            => ['url' => '/index/manager/school-fees',    'position' => 4],
        'supervisor_school_fees' => ['url' => '/index/supervisor/school-fees', 'position' => 7],
    ];

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::FUSIONS as $codeFrais => $codeNiveaux) {
                Menu::where('code', $codeNiveaux)
                    ->update(['title' => self::TITRE_ORIGINE]);

                $niveaux = Menu::where('code', $codeNiveaux)->first();

                // Rien a restaurer si le menu niveaux n'existe pas (jeu de
                // menus jamais seede) ou si l'entree frais est deja la.
                if (! $niveaux || Menu::where('code', $codeFrais)->exists()) {
                    continue;
                }

                $menu = Menu::create([
                    'code'        => $codeFrais,
                    'title'       => 'Frais scolaires',
                    'type'        => 'item',
                    'classes'     => 'nav-item',
                    'url'         => self::RESTAURATION[$codeFrais]['url'],
                    'icon'        => 'receipt',
                    'breadcrumbs' => true,
                    'position'    => self::RESTAURATION[$codeFrais]['position'],
                ]);

                $roleIds = MenuRole::where('menu_id', $niveaux->id)->pluck('role_id');

                foreach ($roleIds as $roleId) {
                    MenuRole::firstOrCreate([
                        'menu_id' => $menu->id,
                        'role_id' => $roleId,
                    ], ['is_default' => false]);
                }
            }
        });
    }
};
