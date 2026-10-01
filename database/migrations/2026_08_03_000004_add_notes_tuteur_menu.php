<?php

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute « Résultats scolaires » a la navigation des familles.
 *
 * Place en position 1, devant la messagerie : c'est ce que le tuteur vient
 * chercher en premier. Marque `is_default` pour le role tuteur, si bien que la
 * connexion ouvre directement cet ecran plutot que la messagerie.
 *
 * Meme precaution que les autres migrations de menu : sur une base neuve, les
 * roles n'existent pas encore et ce sont les seeders qui feront le travail.
 */
return new class extends Migration
{
    private const CODE = 'notes-tuteur';

    public function up(): void
    {
        $roleTuteur = Role::whereIn('id', [RoleEnum::Tuteur->value])->pluck('id');

        if ($roleTuteur->isEmpty() || Menu::where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function () use ($roleTuteur) {
            $menu = Menu::create([
                'code'        => self::CODE,
                'title'       => 'Résultats scolaires',
                'type'        => 'item',
                'classes'     => 'nav-item',
                'url'         => '/index/tuteur/notes',
                'icon'        => 'school',
                'breadcrumbs' => true,
                'position'    => 1,
            ]);

            foreach ($roleTuteur as $roleId) {
                MenuRole::firstOrCreate([
                    'menu_id' => $menu->id,
                    'role_id' => $roleId,
                ], [
                    // L'ecran d'accueil du tuteur : c'est ici que le mene sa
                    // connexion, la messagerie restant a un clic.
                    'is_default' => true,
                ]);
            }

            // La messagerie cede la place : deux menus « par defaut » pour un
            // meme role laisseraient le choix de l'ecran d'accueil au hasard de
            // l'ordre de lecture.
            $messagerie = Menu::where('code', 'messagerie-tuteur')->first();

            if ($messagerie) {
                MenuRole::where('menu_id', $messagerie->id)
                    ->whereIn('role_id', $roleTuteur)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $menu = Menu::where('code', self::CODE)->first();

            if (! $menu) {
                return;
            }

            MenuRole::where('menu_id', $menu->id)->delete();
            $menu->delete();

            // La messagerie redevient l'accueil des familles, comme avant.
            $messagerie = Menu::where('code', 'messagerie-tuteur')->first();

            if ($messagerie) {
                MenuRole::where('menu_id', $messagerie->id)
                    ->where('role_id', RoleEnum::Tuteur->value)
                    ->update(['is_default' => true]);
            }
        });
    }
};
