<?php

use App\Enums\LienParenteEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le tuteur legal est le responsable financier et administratif de l'eleve.
 * Il vit dans sa propre table plutot que dans des colonnes de « eleves » :
 * une fratrie partage un meme tuteur, et le NIN doit rester unique a l'echelle
 * de l'etablissement (contrats financiers, recouvrement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuteurs', function (Blueprint $table) {
            $table->id();

            $table->enum('lien_parente', array_column(LienParenteEnum::cases(), 'value'));

            $table->string('nom');
            $table->string('prenom');

            // Numero d'Identification Nationale (CNI senegalaise). Nullable
            // car parfois inconnu a l'inscription, mais unique lorsqu'il est
            // renseigne : deux tuteurs ne peuvent partager une meme identite.
            $table->string('nin')->nullable()->unique();

            $table->string('telephone_principal');
            $table->string('telephone_secondaire')->nullable();
            $table->string('email')->nullable();
            $table->string('profession')->nullable();
            $table->string('adresse');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuteurs');
    }
};
