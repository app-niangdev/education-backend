<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue la relance d'un arriere (envoyee par le tresorier) du rappel
 * d'une echeance a venir (envoye par le planificateur).
 *
 * Le rappel n'a pas d'auteur : « utilisateur_id » devient facultatif, et vaut
 * null pour tout envoi automatique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relances_paiement', function (Blueprint $table) {
            $table->enum('type', ['RELANCE', 'RAPPEL'])->default('RELANCE')->after('utilisateur_id');
            $table->foreignId('utilisateur_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('relances_paiement', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
