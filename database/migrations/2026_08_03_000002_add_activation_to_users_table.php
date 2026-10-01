<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * L'activation de compte par lien remplace le mot de passe commun.
 *
 * Jusqu'ici, tout compte cree recevait la meme chaine en dur — « P@sser26 »,
 * ecrite dans quatre services et versionnee dans le depot. Quiconque la
 * connaissait pouvait ouvrir n'importe quel compte tant que son titulaire ne
 * s'en etait pas servi. Desormais le compte nait sans mot de passe utilisable
 * et son proprietaire le choisit lui-meme, par un lien a usage unique.
 *
 * Deux effets ici :
 *
 *  1. `email_verified_at` : la colonne manquait a ce projet, alors que le
 *     modele etend Authenticatable et la caste deja en date. C'est elle qui
 *     porte la trace de l'activation — cliquer le lien prouve l'adresse.
 *
 *  2. Les comptes qui n'ont jamais servi voient leur mot de passe rendu
 *     inutilisable. Retirer la constante du code ne suffisait pas : elle reste
 *     valable sur les comptes deja crees. Ceux dont le titulaire s'est deja
 *     connecte — donc a choisi son mot de passe — ne sont pas touches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        // Le critere est le mot de passe lui-meme, et non `must_change_password` :
        // ce drapeau retombe a false par d'autres chemins que la connexion, et
        // des comptes jamais ouverts se retrouvent a false tout en portant
        // encore la constante. Seul un test direct ne laisse rien passer.
        $constante = 'P@sser26';
        $neutralises = [];

        foreach (DB::table('users')->select('id', 'password')->get() as $ligne) {
            if (! Hash::check($constante, $ligne->password)) {
                continue;
            }

            // Un aleatoire different par compte, hache : aucune chaine connue
            // n'ouvre plus ces comptes. Leurs titulaires passeront par le lien
            // d'activation, que l'ecran d'administration sait renvoyer.
            DB::table('users')->where('id', $ligne->id)->update([
                'password'             => Hash::make(Str::random(64)),
                'must_change_password' => false,
            ]);

            $neutralises[] = $ligne->id;
        }

        if ($neutralises !== []) {
            Log::warning('Comptes portant le mot de passe commun : acces neutralise.', [
                'user_ids' => $neutralises,
                'suite'    => 'Renvoyer un lien d\'activation depuis l\'ecran des utilisateurs.',
            ]);
        }

        // Les comptes qui ne portaient pas la constante ont un mot de passe
        // choisi par leur titulaire : leur adresse est donc eprouvee, et leur
        // reclamer une activation n'aurait pas de sens.
        DB::table('users')
            ->whereNotIn('id', $neutralises ?: [0])
            ->whereNotNull('email')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Les mots de passe neutralises ne se restaurent pas : ils n'ont
        // jamais ete connus de personne. Seule la colonne se retire.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
