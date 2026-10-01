<?php

use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un fil de discussion entre un tuteur et un service de l'etablissement.
 *
 * La conversation est adressee a un SERVICE, pas a une personne : c'est le
 * point central du modele. Un tuteur qui ecrit a la tresorerie doit obtenir
 * une reponse meme si le tresorier est absent ; n'importe quel agent habilite
 * reprend le fil. On evite ainsi les messages qui dorment dans la boite d'un
 * seul destinataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Le tuteur est toujours l'une des deux parties : c'est lui qui
            // identifie le fil cote famille, quel que soit l'agent qui repond.
            $table->foreignId('tuteur_id')
                ->constrained('tuteurs')
                ->cascadeOnDelete();

            // L'eleve concerne, quand la demande porte sur un enfant precis.
            // Nullable : une question de facturation ou un rendez-vous ne vise
            // pas forcement un eleve en particulier.
            $table->foreignId('eleve_id')
                ->nullable()
                ->constrained('eleves')
                ->nullOnDelete();

            $table->enum('service', ServiceDestinataireEnum::valeurs());

            $table->string('sujet');

            $table->enum('statut', StatutConversationEnum::valeurs())
                ->default(StatutConversationEnum::OUVERTE->value);

            // L'agent qui a pris le fil en charge. Indicatif, pas exclusif :
            // il signale « quelqu'un s'en occupe » sans empecher un collegue
            // de repondre si l'agent est absent.
            $table->foreignId('agent_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Le service d'ou vient l'escalade, conserve pour la tracabilite :
            // la direction doit savoir qui n'a pas pu trancher, et le fil peut
            // etre redescendu.
            $table->enum('service_origine', ServiceDestinataireEnum::valeurs())->nullable();
            $table->timestamp('escaladee_at')->nullable();
            $table->text('motif_escalade')->nullable();

            // Denormalise depuis messages : la liste des conversations se trie
            // par activite recente, et un tri sur un MAX() correle coute cher
            // des que les fils se comptent en milliers.
            $table->timestamp('derniere_activite_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Le fil d'un tuteur, du plus recent au plus ancien : la requete
            // que fait l'espace tuteur a chaque ouverture.
            $table->index(['tuteur_id', 'derniere_activite_at']);

            // La corbeille d'un service, triee par activite : la requete que
            // font les agents. Le statut precede la date car on filtre d'abord
            // sur « ce qui est encore a traiter ».
            $table->index(['service', 'statut', 'derniere_activite_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
