<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'unicite de l'email ne vise plus que les comptes vivants.
 *
 * L'index pose par la migration initiale couvrait toute la table, comptes
 * revoques compris : l'adresse d'un employe parti restait confisquee a vie, et
 * l'etablissement ne pouvait plus la reattribuer — pas meme a la meme
 * personne, revenue sous un nouveau contrat.
 *
 * C'est exactement le raisonnement deja tenu pour `phone_one`
 * (users_phone_one_unique_active) : la base autorise la reutilisation apres un
 * vrai nettoyage, le controle applicatif restant libre d'etre plus strict.
 *
 * La colonne reste `nullable` : des comptes anterieurs a cette regle n'ont pas
 * d'adresse, et les rendre invalides d'un coup les empecherait d'etre modifies.
 * L'obligation est portee par les formulaires de creation du personnel
 * (enseignant, surveillant, tresorier), la ou elle a un sens.
 */
return new class extends Migration
{
    private const NOM = 'users_email_unique_active';

    public function up(): void
    {
        // `->unique()` cree une CONTRAINTE, pas un simple index : Postgres
        // refuse un DROP INDEX tant que la contrainte s'y appuie. C'est donc
        // elle qu'on leve, et son index disparait avec.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        // Filet, si une base l'avait en index nu plutot qu'en contrainte.
        DB::statement('DROP INDEX IF EXISTS users_email_unique');

        // NULL n'entre jamais en collision avec NULL dans un index unique :
        // les comptes sans adresse coexistent sans se gener.
        DB::statement(
            'CREATE UNIQUE INDEX ' . self::NOM . ' ON users (email) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::NOM);
        // Symetrique du up() : on retablit une contrainte, telle que
        // `->unique()` l'avait posee, et non un index nu.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
    }
};
