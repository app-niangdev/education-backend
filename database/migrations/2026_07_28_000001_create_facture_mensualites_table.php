<?php

use App\Enums\ModePaiementEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regroupe plusieurs mois regles en une seule fois sous un document unique.
 *
 * Jusqu'ici un versement couvrant trois mois produisait trois PaiementMensualite
 * independants, donc trois justificatifs a imprimer un par un. La facture porte
 * desormais le numero, le mode de paiement et la date : les PaiementMensualite
 * restent la ligne comptable par mois (l'imputation reste exacte), mais ils
 * pointent tous vers la meme facture, qui fait office d'entete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_mensualites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero_facture')->unique();
            $table->integer('montant_total');
            $table->enum('mode_paiement', array_column(ModePaiementEnum::cases(), 'value'));
            $table->string('numero_transaction')->nullable();
            $table->date('date_paiement');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['inscription_id', 'date_paiement']);
        });

        // Rattachement optionnel : les versements mois par mois deja emis, et
        // ceux qui continueront de l'etre, n'ont pas de facture parente.
        Schema::table('paiement_mensualites', function (Blueprint $table) {
            $table->foreignId('facture_mensualite_id')
                ->nullable()
                ->after('mensualite_id')
                ->constrained('facture_mensualites')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiement_mensualites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facture_mensualite_id');
        });

        Schema::dropIfExists('facture_mensualites');
    }
};
