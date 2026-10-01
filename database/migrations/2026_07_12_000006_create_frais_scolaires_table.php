<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Grille tarifaire par combinaison (Annee scolaire + Niveau). Les frais
     * peuvent ainsi evoluer d'une annee a l'autre, contrairement aux montants
     * portes par le niveau qui servent uniquement de valeurs par defaut.
     */
    public function up(): void
    {
        Schema::create('frais_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->integer('montant_inscription');
            $table->integer('montant_mensualite');
            // Nombre de mensualites reellement payees. 9 en general, 8 lorsque le
            // 9e mois est integre aux frais d'inscription (pratique au Senegal).
            $table->unsignedTinyInteger('nombre_mensualites')->default(9);
            // Frais annuel = inscription + mensualite x nombre_mensualites.
            // Recalcule cote modele, mais persiste pour l'historique tarifaire.
            $table->integer('frais_annuel');
            // Indique que le 9e mois a ete integre aux frais d'inscription :
            // purement informatif pour la fiche de renseignement.
            $table->boolean('neuvieme_mois_inclus')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Un seul bareme par (annee scolaire, niveau).
            $table->unique(['annee_scolaire_id', 'niveau_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frais_scolaires');
    }
};
