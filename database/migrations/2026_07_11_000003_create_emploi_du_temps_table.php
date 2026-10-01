<?php

use App\Enums\JourSemaineEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emploi du temps : un creneau = une affectation (classe x matiere x enseignant)
 * placee un jour donne, de heure_debut a heure_fin.
 *
 * On stocke aussi classe_id (denormalise depuis l'affectation) pour indexer et
 * detecter les conflits d'une classe sans jointure ; l'annee scolaire cadre le
 * planning (un meme couple change d'une annee a l'autre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('affectation_id')->constrained('affectations')->cascadeOnDelete();
            $table->enum('jour', array_column(JourSemaineEnum::cases(), 'value'));
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classe_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps');
    }
};
