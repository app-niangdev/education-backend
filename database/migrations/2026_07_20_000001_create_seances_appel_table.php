<?php

use App\Enums\StatutSeanceEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'instanciation d'un créneau de l'emploi du temps à une date donnée.
 *
 * `emploi_du_temps` reste le gabarit hebdomadaire pur : rien n'est matérialisé
 * à l'avance. Une séance naît le jour où l'appel est fait. Aucun job de
 * génération, aucune ligne fantôme, et surtout un changement d'emploi du temps
 * en cours d'année ne réécrit jamais le passé.
 *
 * Cette table existe pour distinguer « l'appel n'a pas été fait » de « tout le
 * monde était présent ». Sans elle, une classe sans absence signalée serait
 * indiscernable d'une classe où personne ne fait l'appel — et le chiffre porté
 * au bulletin serait invérifiable.
 *
 * Les horaires et la durée sont recopiés du créneau, pas lus par la relation :
 * si un créneau de 8h-10h passe à 10h-11h en février, les séances de septembre
 * à janvier doivent conserver leurs deux heures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances_appel', function (Blueprint $table) {
            $table->id();

            // Le créneau d'origine. nullOnDelete : supprimer un créneau ne doit
            // pas effacer l'historique d'assiduité, les colonnes dénormalisées
            // ci-dessous suffisent à le relire.
            $table->foreignId('emploi_du_temps_id')->nullable()
                ->constrained('emploi_du_temps')->nullOnDelete();

            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('affectation_id')->nullable()
                ->constrained('affectations')->nullOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();

            // Résolue à l'écriture. Null si la date ne tombe dans aucune période
            // (vacances) : la séance est enregistrée mais ne compte au bulletin
            // d'aucune période.
            $table->foreignId('periode_id')->nullable()
                ->constrained('periodes')->nullOnDelete();

            $table->date('date_seance');

            // Figés à la création (voir en-tête).
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->unsignedSmallInteger('duree_minutes');

            $table->enum('statut', array_column(StatutSeanceEnum::cases(), 'value'))
                ->default(StatutSeanceEnum::FAITE->value);

            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('saisie_le')->nullable();
            $table->text('commentaire')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['classe_id', 'date_seance']);
            $table->index(['affectation_id', 'date_seance']);
            $table->index(['periode_id', 'statut']);
        });

        // Une seule séance vivante par (créneau × date). Index partiel : un
        // unique classique compterait les lignes soft-deleted et empêcherait
        // de refaire l'appel après suppression.
        DB::statement(
            'CREATE UNIQUE INDEX seances_appel_creneau_date_unique
             ON seances_appel (emploi_du_temps_id, date_seance)
             WHERE deleted_at IS NULL AND emploi_du_temps_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS seances_appel_creneau_date_unique');
        Schema::dropIfExists('seances_appel');
    }
};
