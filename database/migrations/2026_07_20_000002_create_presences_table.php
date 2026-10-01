<?php

use App\Enums\StatutPresenceEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui a été relevé pour un élève sur une séance.
 *
 * Seules les anomalies sont stockées : un élève présent ne produit aucune
 * ligne. Enregistrer les présents coûterait des centaines de milliers de
 * lignes par classe et par an pour une information qui se déduit par
 * différence — et l'absence de ligne est sûre puisque la séance atteste que
 * l'appel a bien eu lieu.
 *
 * Le justificatif scanné n'est pas une colonne : il est porté par
 * medialibrary (collection `justificatif`, disque `justificatifs`), comme le
 * logo de l'établissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seance_appel_id')->constrained('seances_appel')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();

            $table->enum('statut', array_column(StatutPresenceEnum::cases(), 'value'));

            // Renseigné seulement pour un RETARD.
            $table->unsignedSmallInteger('minutes_retard')->nullable();

            $table->boolean('justifie')->default(false);
            $table->text('motif')->nullable();

            // Traçabilité : la justification est un acte de la vie scolaire.
            $table->foreignId('justifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('justifie_le')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['eleve_id', 'statut']);
            $table->index(['seance_appel_id']);
        });

        // Un élève n'apparaît qu'une fois par séance. Index partiel, même
        // raison que sur `seances_appel`.
        DB::statement(
            'CREATE UNIQUE INDEX presences_seance_eleve_unique
             ON presences (seance_appel_id, eleve_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS presences_seance_eleve_unique');
        Schema::dropIfExists('presences');
    }
};
