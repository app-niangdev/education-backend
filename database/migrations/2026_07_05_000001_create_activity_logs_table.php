<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');           // ex: created, updated, deleted, login, logout
            $table->string('module');           // ex: users, enseignants, tresoriers
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable(); // classe du modèle concerné
            $table->unsignedBigInteger('subject_id')->nullable(); // id de l'entité concernée
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
