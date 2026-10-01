<?php

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
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('nom_court')->nullable();
            $table->string('slogan')->nullable();
            $table->string('adresse')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('telephone_principal');
            $table->string('telephone_secondaire');
            $table->string('site_web');
            $table->string('lien_facebook')->nullable();
            $table->string('lien_instagram')->nullable();
            $table->string('inspection_academique');
            $table->string('inspection_education_formation');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etablissements');
    }
};
