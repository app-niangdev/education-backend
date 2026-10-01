<?php

use App\Enums\DecisionConseilEnum;
use App\Enums\DistinctionEnum;
use App\Enums\MentionEnum;
use App\Enums\StatutBulletinEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le bulletin d'un élève pour une période.
 *
 * La table est volontairement dénormalisée. Un bulletin publié est remis aux
 * familles et signé : il ne doit plus jamais changer, même si une note est
 * corrigée, un coefficient ajusté ou une matière renommée par la suite. On
 * recopie donc à la publication non seulement les valeurs chiffrées, mais
 * aussi les libellés (état civil, nom de classe) et le contexte (effectif).
 * Sans cela, une simple correction d'état civil réécrirait le passé.
 *
 * Les colonnes `retards` et `absences` figurent sur le bulletin papier mais
 * restent nulles tant que le module d'assiduité n'existe pas : le gabarit PDF
 * les rend en « — ». Elles sont créées dès maintenant pour que l'arrivée de
 * ce module ne demande aucune migration ni retouche du gabarit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            // Dénormalisé : permet de filtrer par année sans passer par la période.
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();

            $table->enum('statut', array_column(StatutBulletinEnum::cases(), 'value'))
                ->default(StatutBulletinEnum::BROUILLON->value);

            // --- Identité figée au moment de la génération -------------------
            $table->string('eleve_nom');
            $table->string('eleve_prenom');
            $table->string('eleve_matricule')->nullable();
            $table->date('eleve_date_naissance')->nullable();
            $table->string('eleve_lieu_naissance')->nullable();
            $table->string('classe_nom');
            // « Classe Redoublée » du bulletin papier, rendu 0 ou 1.
            $table->boolean('classe_redoublee')->default(false);
            // « Nbre d'élèves » : effectif de la classe à la génération.
            $table->unsignedSmallInteger('effectif_classe')->default(0);

            // --- Agrégats calculés -------------------------------------------
            $table->unsignedSmallInteger('total_coefficients')->default(0);
            $table->decimal('total_points', 8, 2)->nullable();
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->boolean('rang_ex_aequo')->default(false);
            $table->enum('mention', array_column(MentionEnum::cases(), 'value'))->nullable();

            // --- Conseil de classe -------------------------------------------
            $table->enum('decision_conseil', array_column(DecisionConseilEnum::cases(), 'value'))->nullable();
            $table->enum('distinction', array_column(DistinctionEnum::cases(), 'value'))->nullable();
            $table->text('observations')->nullable();

            // --- Assiduité (réservé au module à venir) ------------------------
            $table->unsignedSmallInteger('retards')->nullable();
            $table->unsignedSmallInteger('absences')->nullable();

            // --- Traçabilité --------------------------------------------------
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();
            $table->timestamp('genere_le')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['classe_id', 'periode_id', 'statut']);
            $table->index(['periode_id', 'rang']);
        });

        // Un seul bulletin vivant par (élève × période). L'index est partiel :
        // un unique classique compterait les lignes soft-deleted et empêcherait
        // de régénérer un bulletin après suppression.
        DB::statement(
            'CREATE UNIQUE INDEX bulletins_eleve_periode_unique
             ON bulletins (eleve_id, periode_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bulletins_eleve_periode_unique');
        Schema::dropIfExists('bulletins');
    }
};
