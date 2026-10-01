<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tresoriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('matricule')->unique()->nullable();
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])->default('permanent');
            $table->date('date_embauche')->nullable();
            $table->integer('salaire_base')->nullable();
            $table->string('numero_compte_bancaire')->nullable();
            $table->string('banque')->nullable();
            $table->boolean('acces_caisse')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tresoriers');
    }
};
