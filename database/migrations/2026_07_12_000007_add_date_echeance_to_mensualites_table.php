<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Date limite de paiement de la mensualite. Par convention, la mensualite
     * est a regler au plus tard le 5 du mois concerne.
     */
    public function up(): void
    {
        Schema::table('mensualites', function (Blueprint $table) {
            $table->date('date_echeance')->nullable()->after('annee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensualites', function (Blueprint $table) {
            $table->dropColumn('date_echeance');
        });
    }
};
