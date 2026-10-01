<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le telephone devient un identifiant de connexion : deux comptes actifs ne
 * peuvent plus repondre au meme numero.
 *
 * Sans cette contrainte, Auth::attempt(['phone_one' => ...]) choisirait l'une
 * des lignes au hasard — la famille se connecterait tantot sur un compte,
 * tantot sur l'autre, et verrait les conversations d'autrui.
 *
 * Index PARTIEL, sur les seuls comptes vivants :
 *
 *  - « deleted_at IS NULL » : un compte revoque ne doit pas confisquer le
 *    numero d'une famille a vie. Le controle applicatif reste plus strict (il
 *    refuse aussi de reutiliser le numero d'un compte revoque, pour eviter
 *    qu'un ancien acces ne soit confondu avec un nouveau), mais la base, elle,
 *    autorise la reutilisation apres un vrai nettoyage.
 *
 *  - Le personnel partage parfois un numero de service : la contrainte ne vise
 *    que ce qui sert a se connecter, et c'est le role tuteur qui a introduit
 *    l'usage du telephone comme identifiant. On l'applique cependant a tous
 *    les comptes : rien n'empeche demain un surveillant de s'y connecter, et
 *    une ambiguite d'identifiant n'est jamais souhaitable.
 */
return new class extends Migration
{
    private const NOM = 'users_phone_one_unique_active';

    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX ' . self::NOM . ' ON users (phone_one) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::NOM);
    }
};
