<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'etat de lecture d'un fil, par personne.
 *
 * On stocke la lecture ici plutot qu'un booleen « lu » sur chaque message :
 * cote service, un fil est lu par plusieurs agents differents, et marquer
 * chaque message pour chaque agent ferait exploser le volume. Une seule ligne
 * par (conversation, utilisateur) suffit — on retient jusqu'ou la personne a lu.
 *
 * La ligne se cree a la premiere ouverture du fil, pas a sa creation : on ne
 * sait pas d'avance quel agent du service viendra le lire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // L'id du dernier message lu, plutot qu'une date : deux messages
            // peuvent porter le meme timestamp a la seconde pres, l'id non.
            // Le compteur de non-lus devient « messages d'id superieur ».
            $table->unsignedBigInteger('dernier_message_lu_id')->nullable();

            $table->timestamp('lu_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);

            // « Mes fils non lus » : on part de l'utilisateur.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
