<?php

use App\Enums\TypeEvaluationEnum;
use App\Models\Parametrage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une évaluation est créée par l'enseignant pour une de ses affectations
 * (couple classe × matière dont il a la charge) et rattachée à une période
 * de l'année scolaire. Le barème lui est propre (par défaut celui de
 * l'établissement) ; le coefficient est déduit de classe_matiere. Les notes
 * des élèves vivent dans la table `notes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            $table->string('titre');
            $table->enum('type', array_column(TypeEvaluationEnum::cases(), 'value'))
                ->default(TypeEvaluationEnum::DEVOIR_1->value);
            $table->unsignedTinyInteger('bareme')->default(Parametrage::BAREME_DEFAUT);
            $table->date('date_evaluation');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['affectation_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
