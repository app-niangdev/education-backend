<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relances WhatsApp des familles en retard de paiement : une ligne par
 * tentative d'envoi, reussie ou non.
 *
 * La table sert deux choses : espacer les relances d'une meme famille (le
 * numero d'envoi de l'etablissement ne doit pas etre signale comme
 * indesirable), et garder la trace de qui a relance qui, pour quel montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();

            // Le montant reclame et le numero vise AU MOMENT de l'envoi : les
            // deux peuvent changer ensuite, l'historique doit rester exact.
            $table->integer('montant');
            $table->string('telephone', 20);

            $table->enum('statut', ['ENVOYEE', 'ECHEC']);
            $table->string('erreur', 500)->nullable();
            $table->timestamps();

            $table->index(['tuteur_id', 'statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances_paiement');
    }
};
