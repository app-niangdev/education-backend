<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donne au tuteur un compte de connexion.
 *
 * Jusqu'ici le tuteur n'etait qu'une fiche de contact saisie a l'inscription :
 * aucun moyen de s'authentifier. La messagerie avec l'etablissement suppose
 * qu'il ouvre une session, donc qu'il existe dans « users ».
 *
 * La colonne est nullable et le lien facultatif : la grande majorite des
 * tuteurs deja enregistres n'ont pas de compte, et beaucoup n'en auront jamais
 * (pas d'adresse e-mail, pas d'usage du numerique). Le compte se cree a la
 * demande, sans jamais bloquer une inscription.
 *
 * unique() plutot qu'un simple index : un compte utilisateur ne represente
 * qu'un seul tuteur. Deux fiches partageant un compte rendraient impossible de
 * savoir au nom de qui un message est ecrit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                // Le compte disparait (radiation, RGPD) : la fiche tuteur
                // survit, car les eleves et les paiements y sont rattaches.
                ->nullOnDelete();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tuteurs', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
