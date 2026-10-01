<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('matricule')->unique()->nullable();
            $table->string('specialite')->nullable();
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])->default('permanent');
            $table->date('date_embauche')->nullable();
            $table->integer('salaire_base')->nullable();
            $table->text('diplomes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignants');
    }
};
