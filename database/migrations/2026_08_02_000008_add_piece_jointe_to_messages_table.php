<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permet a un message de porter un justificatif.
 *
 * Le fichier n'est PAS stocke : on garde de quoi le reconstruire a la demande.
 * Un recu se regenere a l'identique depuis le paiement, et le refabriquer au
 * clic evite d'accumuler des PDF sur le disque, de les sauvegarder, et surtout
 * de servir un document perime apres une correction comptable.
 *
 * Deux colonnes suffisent :
 *
 *  - piece_jointe_type : ce qu'il faut generer (recu d'inscription, recu de
 *    mensualite, facture multi-mois). Chaine plutot qu'enum SQL : la liste
 *    s'etoffera (bulletins, attestations) et une migration de type enum a
 *    chaque ajout serait couteuse pour un champ purement technique.
 *
 *  - piece_jointe_id : l'identifiant de l'objet source (le paiement).
 *
 * La route de telechargement verifie que le demandeur a bien acces au fil qui
 * porte la piece : c'est elle qui protege le document, pas son emplacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('piece_jointe_type', 40)->nullable()->after('est_systeme');
            $table->unsignedBigInteger('piece_jointe_id')->nullable()->after('piece_jointe_type');

            // Libelle affiche a la place du nom de fichier (« Reçu n° R-0042 »).
            // Fige a l'envoi : le numero de recu ne bouge plus, et le message
            // reste lisible meme si la piece devient introuvable.
            $table->string('piece_jointe_libelle')->nullable()->after('piece_jointe_id');

            // Retrouver les messages portant une piece donnee : sert au moment
            // d'annuler un paiement, pour retirer le justificatif devenu faux.
            $table->index(['piece_jointe_type', 'piece_jointe_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['piece_jointe_type', 'piece_jointe_id']);
            $table->dropColumn([
                'piece_jointe_type',
                'piece_jointe_id',
                'piece_jointe_libelle',
            ]);
        });
    }
};
