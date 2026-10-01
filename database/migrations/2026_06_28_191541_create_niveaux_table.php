<?php

use App\Enums\CycleNiveauEnum;
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
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code');
            $table->integer('montant_inscription');
            $table->integer('montant_mensuel');
            $table->enum('cycle', array_column(CycleNiveauEnum::cases(), 'value'));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 'levels' etait un reste du nommage anglais : le rollback laissait
        // silencieusement la table 'niveaux' derriere lui.
        Schema::dropIfExists('niveaux');
    }
};
