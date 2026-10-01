<?php

use App\Enums\TypeInscriptionEnum;
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
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->enum('type_inscription', array_column(TypeInscriptionEnum::cases(), 'value'))
                ->default(TypeInscriptionEnum::NOUVELLE->value)
                ->after('annee_scolaire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('type_inscription');
        });
    }
};
