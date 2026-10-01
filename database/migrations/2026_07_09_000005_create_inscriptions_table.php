<?php

use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('numero_inscription')->unique();
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('classe_id')->constrained('classes');
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires');
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('montant_inscription');
            $table->enum('statut_inscription', array_column(StatutInscriptionEnum::cases(), 'value'))
                ->default(StatutInscriptionEnum::EN_ATTENTE->value);
            $table->enum('statut_paiement', array_column(StatutPaiementEnum::cases(), 'value'))
                ->default(StatutPaiementEnum::NON_PAYE->value);
            $table->date('date_inscription');
            $table->timestamps();
            $table->softDeletes();

            // Un eleve ne peut etre inscrit qu'une fois par annee scolaire.
            $table->unique(['eleve_id', 'annee_scolaire_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
