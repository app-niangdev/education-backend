<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'authentification en attente de son second facteur.
 *
 * Entre « le mot de passe est bon » et « voici ton jeton », il existe un etat
 * intermediaire : l'utilisateur est reconnu mais pas encore authentifie. Cet
 * etat vit ici, et nulle part ailleurs — surtout pas dans un jeton remis au
 * client, qui reviendrait a livrer la session que l'OTP est cense proteger.
 *
 * Le client ne recoit qu'un `challenge_token` opaque : il designe la ligne sans
 * rien affirmer. Le code a six chiffres, lui, n'est stocke que hache : une
 * fuite de la table ne donne pas de quoi finir les connexions en cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_challenges', function (Blueprint $table) {
            $table->id();

            // Reference remise au client a la place d'une session. Aleatoire,
            // sans signification propre : tout est relu en base a chaque appel.
            $table->string('challenge_token', 64)->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // L'IP d'origine : la validation doit venir d'ou vient la demande.
            $table->string('ip_address', 45);

            $table->string('otp_hash');

            // Pourquoi ce challenge existe. Purement informatif (journal,
            // libelles) : la regle de decision, elle, a deja ete appliquee.
            $table->enum('reason', ['TWO_FACTOR', 'IP_PREVIOUSLY_BLOCKED'])
                  ->default('TWO_FACTOR');

            $table->timestamp('expires_at');

            // Essais de saisie du code, plafonnes pour interdire le balayage
            // des 10^6 combinaisons pendant la duree de vie du challenge.
            $table->unsignedSmallInteger('attempts')->default(0);

            // Renvois du code, comptes a part : reclamer un nouveau code n'est
            // pas se tromper, et les deux plafonds different.
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();

            // Horodate la validation : un challenge verifie ne resservira pas.
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Invalidation des challenges d'un utilisateur lors d'un renvoi de
            // code, et balayage des expires par le nettoyage periodique.
            $table->index(['user_id', 'verified_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_challenges');
    }
};
