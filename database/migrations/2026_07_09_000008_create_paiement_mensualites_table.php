<?php

use App\Enums\ModePaiementEnum;
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
        Schema::create('paiement_mensualites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensualite_id')->constrained('mensualites')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero_recu')->unique();
            $table->integer('montant');
            $table->enum('mode_paiement', array_column(ModePaiementEnum::cases(), 'value'));
            $table->string('numero_transaction')->nullable();
            $table->date('date_paiement');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiement_mensualites');
    }
};
