<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Menu;
use App\Models\MenuRole;
use Illuminate\Database\Seeder;

/**
 * Menus du tresorier. Deux blocs, dans l'ordre du guichet :
 *
 *  1. La saisie — eleves puis inscriptions. L'inscription ouvre les frais et
 *     l'echeancier : c'est un acte de caisse, le tresorier la saisit donc
 *     lui-meme, dans la foulee de la fiche eleve (voir StoreInscriptionRequest).
 *  2. L'encaissement — inscriptions a encaisser, mensualites, paiements —
 *     puis les depenses et le bilan.
 *
 * Le manager conserve la consultation des inscriptions, mais plus leur
 * creation : c'est ce qui evite les eleves crees sans inscription.
 */
class MenuTreasurerSeeder extends Seeder
{
    public function run(): void
    {
        $treasurerId = RoleEnum::Treasurer->value;

        // La position suit le decoupage par dizaine de la sidebar regroupee
        // (voir les migrations add_groupe_to_menus_table,
        // scinde_paiement_et_finances_menus_table,
        // reordonne_groupes_menus_table,
        // permute_scolarite_et_finances_menus_table et
        // deplace_historique_en_fin_de_groupe_paiement_menus_table) : 1-2 les
        // liens directs epingles, puis les groupes dans l'ordre
        // Paiement(10-12, Historique en dernier) / Scolarite(20) /
        // Finances(30-31).
        $dashboard = Menu::create([
            'code'        => 'default_treasurer',
            'title'       => 'Tableau de bord',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/home',
            'icon'        => 'dashboard',
            'breadcrumbs' => true,
            'position'    => 1,
        ]);

        // --- Saisie : la fiche eleve, puis l'inscription qui l'enchaine -------

        $eleves = Menu::create([
            'code'        => 'treasurer_eleves',
            'title'       => 'Élèves',
            'groupe'      => null,
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/students',
            'icon'        => 'contacts',
            'breadcrumbs' => true,
            'position'    => 2,
        ]);

        // Groupee sous Scolarite, avec les inscriptions du manager et du
        // surveillant : elle se retrouve donc apres le bloc Paiement
        // ci-dessous, meme si le geste au guichet l'enchaine juste apres la
        // fiche eleve.
        $inscriptions = Menu::create([
            'code'        => 'treasurer_inscriptions',
            'title'       => 'Inscriptions',
            'groupe'      => 'Scolarité',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/inscriptions',
            // Le jeu d'icones embarque par le frontend est restreint (voir
            // src/assets/img/icons/material-design-icons/two-tone) : une
            // icone absente part en 404 et casse le rendu du menu.
            // « assignment » est celle des deux autres entrees Inscriptions.
            'icon'        => 'assignment',
            'breadcrumbs' => true,
            'position'    => 20,
        ]);

        // --- Encaissement -----------------------------------------------------

        // Le libelle dit l'acte (encaisser) et non l'objet : « Inscriptions »
        // revient a l'ecran de saisie ci-dessus, avec lequel il entrerait
        // sinon en collision.
        $encaissements = Menu::create([
            'code'        => 'treasurer_encaissements',
            'title'       => 'Encaissements',
            'groupe'      => 'Paiement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/encaissements',
            'icon'        => 'receipt',
            'breadcrumbs' => true,
            'position'    => 10,
        ]);

        $mensualites = Menu::create([
            'code'        => 'treasurer_mensualites',
            'title'       => 'Mensualités',
            'groupe'      => 'Paiement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/mensualites',
            'icon'        => 'calendar_today',
            'breadcrumbs' => true,
            'position'    => 11,
        ]);

        // « Historique » et non « Paiements » : dans le groupe « Paiement »,
        // une entree nommee comme le groupe pretait a confusion. En dernier :
        // c'est la consultation de ce que les deux ecrans precedents ont saisi.
        $paiements = Menu::create([
            'code'        => 'treasurer_paiements',
            'title'       => 'Historique',
            'groupe'      => 'Paiement',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/paiements',
            'icon'        => 'assignment_turned_in',
            'breadcrumbs' => true,
            'position'    => 12,
        ]);

        // Depenses et bilan forment leur propre groupe « Finances » : ni
        // l'un ni l'autre n'est un encaissement.
        $depenses = Menu::create([
            'code'        => 'treasurer_depenses',
            'title'       => 'Dépenses',
            'groupe'      => 'Finances',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/expenses',
            'icon'        => 'shopping_bag',
            'breadcrumbs' => true,
            'position'    => 30,
        ]);

        // Le bilan ferme la sequence : il agrege ce que les ecrans precedents
        // ont saisi (encaissements, mensualites, depenses).
        $bilan = Menu::create([
            'code'        => 'treasurer_bilan',
            'title'       => 'Bilan',
            'groupe'      => 'Finances',
            'type'        => 'item',
            'classes'     => 'nav-item',
            'url'         => '/index/treasurer/bilan',
            'icon'        => 'assessment',
            'breadcrumbs' => true,
            'position'    => 31,
        ]);

        MenuRole::insert([
            ['menu_id' => $dashboard->id,     'role_id' => $treasurerId, 'is_default' => true],
            ['menu_id' => $eleves->id,        'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $inscriptions->id,  'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $encaissements->id, 'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $mensualites->id,   'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $paiements->id,     'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $depenses->id,      'role_id' => $treasurerId, 'is_default' => false],
            ['menu_id' => $bilan->id,         'role_id' => $treasurerId, 'is_default' => false],
        ]);
    }
}
