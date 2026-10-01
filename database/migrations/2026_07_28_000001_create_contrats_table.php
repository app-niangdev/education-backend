<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le contrat de travail du personnel, comme entite a part entiere.
 *
 * Jusqu'ici l'engagement se resumait a trois colonnes posees sur chaque table
 * de profil (`type_contrat`, `date_embauche`, `salaire_base`). Cela ne decrivait
 * qu'un etat present : impossible de dire quand un contrat s'acheve, s'il a ete
 * resilie, ni ce qu'il remplace. Un renouvellement ecrasait le precedent.
 *
 * La relation est polymorphe car enseignant, tresorier et surveillant sont trois
 * tables distinctes : une cle etrangere classique obligerait a trois colonnes
 * nullables dont une seule serait remplie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();

            // L'employe engage : enseignants / tresoriers / surveillants.
            $table->morphs('contractable');

            $table->string('numero_contrat')->unique();
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])
                  ->default('permanent');
            $table->enum('statut', ['BROUILLON', 'ACTIF', 'EXPIRE', 'RESILIE', 'SUSPENDU'])
                  ->default('ACTIF');

            // Dates & periode d'essai. `date_fin` reste vide pour un permanent,
            // engage sans terme ; elle est exigee des vacataires et stagiaires.
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->unsignedSmallInteger('duree_periode_essai')->nullable(); // en mois

            // Remuneration : le mode dit comment lire le montant. Il vivait sur
            // les enseignants seuls, alors qu'un surveillant vacataire peut lui
            // aussi etre paye a l'heure.
            $table->integer('salaire_base')->nullable();
            $table->enum('mode_remuneration', ['MENSUEL', 'HORAIRE'])->default('MENSUEL');

            // Conditions de travail : ce qu'un contrat imprime doit mentionner.
            $table->string('fonction')->nullable();
            $table->string('lieu_travail')->nullable();
            $table->unsignedSmallInteger('volume_horaire_hebdo')->nullable();

            // Fin anticipee : le motif n'a de sens qu'avec la date.
            $table->date('date_resiliation')->nullable();
            $table->text('motif_resiliation')->nullable();

            // Chainage des renouvellements et avenants. On ne cascade pas la
            // suppression : perdre un contrat ne doit pas effacer son historique.
            $table->foreignId('contrat_parent_id')
                  ->nullable()
                  ->constrained('contrats')
                  ->nullOnDelete();

            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Retrouver le contrat en cours d'un employe est la lecture la plus
            // frequente : elle passe par ce couple.
            $table->index(['contractable_type', 'contractable_id', 'statut'], 'contrats_contractable_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
