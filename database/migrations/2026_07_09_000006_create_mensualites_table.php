<?php

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
        Schema::create('mensualites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->integer('montant_mensualite');
            $table->enum('statut', array_column(StatutPaiementEnum::cases(), 'value'))
                ->default(StatutPaiementEnum::NON_PAYE->value);
            $table->timestamps();
            $table->softDeletes();

            // Une seule mensualite par mois pour une inscription donnee.
            $table->unique(['inscription_id', 'mois', 'annee']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensualites');
    }
};
