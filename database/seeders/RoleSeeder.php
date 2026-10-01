<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'admin',      'label' => 'Administrateur'],
            ['id' => 2, 'name' => 'manager',    'label' => 'Directeur'],
            ['id' => 3, 'name' => 'supervisor', 'label' => 'Surveillant'],
            ['id' => 4, 'name' => 'treasurer',  'label' => 'Trésorier'],
            ['id' => 5, 'name' => 'teacher',    'label' => 'Enseignant'],
            ['id' => 6, 'name' => 'tuteur',     'label' => 'Tuteur'],
        ];

        // Idempotent : rejouable sans violer la clé primaire.
        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }

        // Les id sont posés à la main : la séquence PostgreSQL, elle, est
        // restée à son point de départ. Sans ce recalage, la première création
        // de rôle faite par l'application rejouerait l'id 1, déjà pris.
        DB::statement("SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT MAX(id) FROM roles))");
    }
}
