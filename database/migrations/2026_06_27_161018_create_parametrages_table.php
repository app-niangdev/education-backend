<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametrages', function (Blueprint $table) {
            $table->id();
            $table->text('telephone_transaction')->nullable();
            $table->boolean('statut')->default(true);
            $table->boolean('en_maintenance')->default(false);
            $table->string('code_couleur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametrages');
    }
};
