<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute l'entree « Messagerie » a la navigation.
 *
 * Deux entrees distinctes plutot qu'une seule : l'agent et le tuteur ne voient
 * pas la meme chose. L'agent ouvre la corbeille de son service ; le tuteur
 * ouvre ses propres fils. Les URL different, donc les menus aussi.
 *
 * Meme precaution que les autres migrations de menu : sur une base neuve, les
 * roles n'existent pas encore et ce sont les seeders qui feront le travail.
 */
return new class extends Migration
{
    private const CODE_AGENT  = 'messagerie';
    private const CODE_TUTEUR = 'messagerie-tuteur';

    public function up(): void
    {
        // La corbeille du service : tous ceux qui traitent des demandes.
        $rolesAgent = Role::whereIn('id', [
            RoleEnum::Admin->value,
            RoleEnum::Manager->value,
            RoleEnum::Supervisor->value,
            RoleEnum::Treasurer->value,
        ])->pluck('id');

        $roleTuteur = Role::whereIn('id', [RoleEnum::Tuteur->value])->pluck('id');

        if ($rolesAgent->isEmpty() && $roleTuteur->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rolesAgent, $roleTuteur) {
            $this->creerMenu(self::CODE_AGENT, 'Messagerie', '/index/messagerie', 'forum', 12, $rolesAgent);
            $this->creerMenu(self::CODE_TUTEUR, 'Mes échanges', '/index/tuteur/messagerie', 'forum', 2, $roleTuteur);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach ([self::CODE_AGENT, self::CODE_TUTEUR] as $code) {
                $menu = Menu::where('code', $code)->first();

                if (!$menu) {
                    continue;
                }

                MenuRole::where('menu_id', $menu->id)->delete();
                $menu->delete();
            }
        });
    }

    private function creerMenu(
        string $code,
        string $titre,
        string $url,
        string $icone,
        int $position,
        \Illuminate\Support\Collection $roleIds,
    ): void {
        if ($roleIds->isEmpty() || Menu::where('code', $code)->exists()) {
            return;
        }

        $menu = Menu::create([
            'code'        => $code,
            'title'       => $titre,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => $url,
            'icon'        => $icone,
            'breadcrumbs' => true,
            'position'    => $position,
        ]);

        foreach ($roleIds as $roleId) {
            MenuRole::firstOrCreate([
                'menu_id' => $menu->id,
                'role_id' => $roleId,
            ], ['is_default' => false]);
        }
    }
};
