<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les messages d'une conversation.
 *
 * L'expediteur est un utilisateur (tuteur ou agent), jamais un service : on
 * doit pouvoir dire « Mme Diop, de la tresorerie, a repondu ceci », pas
 * seulement « la tresorerie a repondu ». Le service repond du fil, la personne
 * repond du message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            // Nullable : le compte supprime, le message reste. Retirer une
            // reponse d'un fil parce que l'agent a quitte l'ecole rendrait
            // l'echange incomprehensible pour la famille comme pour l'audit.
            $table->foreignId('expediteur_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('corps');

            // Message de service (« fil escalade a la direction », « pris en
            // charge par X ») plutot que parole d'un humain. Affiche
            // differemment cote client, et jamais compte comme non-lu.
            $table->boolean('est_systeme')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Le fil affiche dans l'ordre chronologique : l'index porte les
            // deux colonnes du tri, id departageant les messages d'une meme
            // seconde.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
