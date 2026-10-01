<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La note d'un élève à une évaluation. `valeur` est exprimée sur le barème
 * de l'évaluation (0 → bareme). Une seule note par (évaluation × élève) :
 * la saisie est un upsert côté service. `valeur` nullable pour permettre de
 * marquer une absence (ABS) sans note chiffrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->decimal('valeur', 5, 2)->nullable();
            $table->boolean('absent')->default(false);
            $table->string('appreciation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['evaluation_id', 'eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
