<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'etat de securite d'une adresse IP face au formulaire de connexion.
 *
 * Une ligne par IP, creee au premier echec et conservee ensuite : c'est elle
 * qui porte la memoire du blocage progressif. `block_level` ne retombe jamais
 * seul — seule une authentification menee jusqu'au bout le remet a zero — de
 * sorte qu'un attaquant qui attend la fin d'un blocage repart au palier
 * suivant, pas au premier.
 *
 * `was_blocked` est distinct de `blocked_until` : le second dit « bloque en ce
 * moment », le premier « a ete bloque depuis la derniere connexion reussie ».
 * C'est ce drapeau, et non l'expiration du blocage, qui rend l'OTP obligatoire
 * a la connexion suivante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_security', function (Blueprint $table) {
            $table->id();

            // IPv6 tient en 45 caracteres ; l'unicite fait la cle metier.
            $table->string('ip_address', 45)->unique();

            // Echecs consecutifs depuis la derniere reussite. Sert au seuil.
            $table->unsignedInteger('failed_attempts')->default(0);

            // Palier atteint : 0 = jamais bloquee. La duree du prochain blocage
            // se deduit de ce niveau (1 min x 2^(niveau-1), plafonnee a 24 h).
            $table->unsignedSmallInteger('block_level')->default(0);

            // Fin du blocage en cours. Nulle si l'IP n'est pas bloquee.
            $table->timestamp('blocked_until')->nullable();

            // Un blocage a eu lieu et n'a pas encore ete « purge » par une
            // authentification complete : la prochaine reussite exigera un OTP.
            $table->boolean('was_blocked')->default(false);

            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('last_success_at')->nullable();

            $table->timestamps();

            // Le middleware interroge les IP encore sous blocage a chaque
            // requete ; le nettoyage periodique balaie les lignes dormantes.
            $table->index('blocked_until');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_security');
    }
};
