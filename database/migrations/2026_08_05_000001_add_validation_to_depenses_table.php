<?php

use App\Enums\StatutDepenseEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le circuit de validation des depenses.
 *
 * Le tresorier saisit, le manager engage. Une depense reste EN_ATTENTE jusqu'a
 * ce qu'un manager (ou l'admin) la valide, et n'entre dans les totaux ni dans
 * le bilan qu'une fois validee.
 *
 * Les depenses deja enregistrees sont reputees VALIDEES : elles figurent
 * depuis toujours dans le bilan, et les basculer en attente ferait chuter d'un
 * coup le total des depenses de l'annee — un exercice deja arrete se mettrait
 * a mentir. Le defaut de la colonne, lui, est bien EN_ATTENTE : il ne vaut que
 * pour les saisies a venir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->enum('statut', StatutDepenseEnum::valeurs())
                ->default(StatutDepenseEnum::EN_ATTENTE->value)
                ->after('description');

            // Qui a tranche, et quand. Nullable tant que la decision n'est pas
            // prise. La suppression d'un compte ne doit pas effacer la trace
            // de la validation : on detache plutot que de cascader.
            $table->foreignId('validateur_id')
                ->nullable()
                ->after('statut')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('valide_le')->nullable()->after('validateur_id');

            // Le motif du refus. Obligatoire au refus (regle portee par la
            // validation), il explique au tresorier ce qui a ete ecarte.
            $table->text('motif_refus')->nullable()->after('valide_le');

            // Le filtre principal du module : « qu'est-ce qui attend ma
            // decision », et l'exclusion des non-validees dans les totaux.
            $table->index(['statut', 'date_depense']);
        });

        // L'historique anterieur au circuit : valide d'office (voir en-tete).
        DB::table('depenses')->update([
            'statut' => StatutDepenseEnum::VALIDEE->value,
        ]);
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropIndex(['statut', 'date_depense']);
            $table->dropConstrainedForeignId('validateur_id');
            $table->dropColumn(['statut', 'valide_le', 'motif_refus']);
        });
    }
};
