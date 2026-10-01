<?php

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les depenses de l'etablissement, rattachees a une annee scolaire.
 *
 * Le rattachement est explicite plutot que deduit de la date : une depense
 * engagee pendant les vacances peut relever de l'annee qui s'acheve comme de
 * celle qui s'ouvre, et seul le saisisseur peut trancher. C'est aussi ce qui
 * permet de filtrer « les mois de l'annee scolaire en cours » sans ambiguite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            // L'auteur de la saisie. Nullable : la suppression d'un compte ne
            // doit pas emporter l'historique comptable.
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('libelle');
            $table->enum('categorie', CategorieDepenseEnum::valeurs());
            $table->integer('montant');
            $table->enum('mode_paiement', array_column(ModePaiementEnum::cases(), 'value'));
            $table->string('beneficiaire')->nullable();
            $table->string('reference')->nullable();
            $table->date('date_depense');
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Les trois filtres du module : par date, par periode, par annee.
            $table->index('date_depense');
            $table->index(['annee_scolaire_id', 'date_depense']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
