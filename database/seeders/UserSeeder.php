<?php

namespace Database\Seeders;

use App\Enums\ModeRemunerationEnum;
use App\Enums\StatutContratEnum;
use App\Enums\TypeContratEnum;
use App\Models\Contrat;
use App\Models\Enseignant;
use App\Models\Role;
use App\Models\Surveillant;
use App\Models\Tresorier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        // ── Admin ─────────────────────────────────────────────────────────────
        User::create([
            'first_name' => 'Ibrahima',
            'last_name'  => 'Niang',
            'email'      => 'niangdev031299@gmail.com',
            'phone_one'  => '770906538',
            'address'    => 'Dakar',
            'password'   => 'P@sser1234',
            'status'     => true,
            'role_id'    => $roles['admin'],
        ]);

        // ── Manager (Directeur) ───────────────────────────────────────────────
        User::create([
            'first_name' => 'Mansour',
            'last_name'  => 'Gueye',
            'email'      => 'mansourgueye614@gmail.com',
            'phone_one'  => '770000002',
            'address'    => 'Thiès',
            'password'   => 'P@sser1234',
            'status'     => true,
            'role_id'    => $roles['manager'],
        ]);

        // // ── Surveillant ───────────────────────────────────────────────────────
        // $surveillantUser = User::create([
        //     'first_name' => 'Fatou',
        //     'last_name'  => 'Sow',
        //     'email'      => 'surveillant@yopmail.com',
        //     'phone_one'  => '770000003',
        //     'address'    => 'Rufisque',
        //     'password'   => 'P@sser1234',
        //     'status'     => true,
        //     'role_id'    => $roles['supervisor'],
        // ]);

        // $surveillant = Surveillant::create([
        //     'user_id'          => $surveillantUser->id,
        //     'matricule'        => 'SUR-001',
        //     'zone_surveillance'=> 'Cour principale',
        //     'horaire'          => 'Matin',
        // ]);

        // // Les conditions d'engagement vivent sur le contrat, plus sur le profil.
        // $this->contrat($surveillant, 'CTR-2022-0001', 'Surveillant', '2022-09-01', 120000);

        // // ── Trésorier ─────────────────────────────────────────────────────────
        // $tresorierUser = User::create([
        //     'first_name' => 'Amadou',
        //     'last_name'  => 'Bâ',
        //     'email'      => 'tresorier@yopmail.com',
        //     'phone_one'  => '770000004',
        //     'address'    => 'Kaolack',
        //     'password'   => 'P@sser1234',
        //     'status'     => true,
        //     'role_id'    => $roles['treasurer'],
        // ]);

        // $tresorier = Tresorier::create([
        //     'user_id'               => $tresorierUser->id,
        //     'matricule'             => 'TRE-001',
        //     'numero_compte_bancaire'=> 'SN28001000150000123456789',
        //     'banque'                => 'CBAO',
        //     'acces_caisse'          => true,
        // ]);

        // $this->contrat($tresorier, 'CTR-2021-0001', 'Trésorier', '2021-09-01', 180000);

        // // ── Enseignant ────────────────────────────────────────────────────────
        // $enseignantUser = User::create([
        //     'first_name' => 'Mariama',
        //     'last_name'  => 'Fall',
        //     'email'      => 'enseignant@yopmail.com',
        //     'phone_one'  => '770000005',
        //     'address'    => 'Saint-Louis',
        //     'password'   => 'P@sser1234',
        //     'status'     => true,
        //     'role_id'    => $roles['teacher'],
        // ]);

        // // Les matières de spécialité se rattachent depuis la fiche enseignant :
        // // le référentiel des matières n'est pas semé (il est saisi par le manager).
        // $enseignant = Enseignant::create([
        //     'user_id'      => $enseignantUser->id,
        //     'matricule'    => 'ENS-001',
        //     'diplomes'     => 'Licence MIAS, Master Enseignement',
        // ]);

        // $this->contrat($enseignant, 'CTR-2020-0001', 'Enseignant', '2020-09-01', 200000);
    }

    /**
     * Le contrat permanent d'un membre du personnel seme.
     *
     * Numeros fixes plutot que calcules : le seeder doit produire deux fois le
     * meme jeu de donnees, et rejouer un compteur donnerait des references
     * differentes a chaque execution.
     */
    // private function contrat(
    //     Model  $contractable,
    //     string $numero,
    //     string $fonction,
    //     string $debut,
    //     int    $salaire,
    // ): void {
    //     Contrat::create([
    //         'contractable_type' => $contractable->getMorphClass(),
    //         'contractable_id'   => $contractable->getKey(),
    //         'numero_contrat'    => $numero,
    //         'type_contrat'      => TypeContratEnum::PERMANENT->value,
    //         'statut'            => StatutContratEnum::ACTIF->value,
    //         'date_debut'        => $debut,
    //         'salaire_base'      => $salaire,
    //         'mode_remuneration' => ModeRemunerationEnum::MENSUEL->value,
    //         'fonction'          => $fonction,
    //     ]);
    // }
}
