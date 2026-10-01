<?php

use App\Enums\RoleEnum;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le role « tuteur » aux installations DEJA seedees : RoleSeeder porte
 * desormais la ligne, mais il ne rejoue pas tout seul sur une base en service.
 *
 * Sur une base neuve (migrate:fresh), les migrations tournent avant les
 * seeders : la table est vide, updateOrCreate cree la ligne, et RoleSeeder la
 * retrouvera ensuite sans la dupliquer (il est idempotent lui aussi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = RoleEnum::Tuteur->value;

        if (DB::table('roles')->where('id', $id)->exists()) {
            return;
        }

        // Insertion via le query builder, id compris : « id » n'est pas dans
        // le $fillable du modele, si bien qu'un updateOrCreate le laisserait
        // de cote et retomberait sur la sequence PostgreSQL — laquelle est
        // restee a 1, les roles ayant ete inseres avec des id explicites.
        DB::table('roles')->insert([
            'id'         => $id,
            'name'       => 'tuteur',
            'label'      => 'Tuteur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // La sequence doit repartir apres le dernier id pose a la main, sinon
        // la premiere creation de role par l'application rejouerait l'id 1.
        DB::statement("SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT MAX(id) FROM roles))");
    }

    public function down(): void
    {
        // Les comptes tuteurs pointent sur ce role : les laisser orphelins
        // casserait la connexion. On ne redescend que si personne ne l'utilise.
        $role = Role::query()->find(RoleEnum::Tuteur->value);

        if ($role !== null && !$role->users()->exists()) {
            $role->delete();
        }
    }
};
