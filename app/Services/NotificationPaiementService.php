<?php

namespace App\Services;

use App\Enums\PieceJointeEnum;
use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use App\Events\MessageEnvoye;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\NotificationPaiementServiceInterface;
use App\Models\Conversation;
use App\Models\Eleve;
use App\Models\Tuteur;
use Illuminate\Support\Facades\Log;

/**
 * Depose l'accuse de paiement dans la messagerie de la famille.
 *
 * Trois principes commandent ce service :
 *
 * 1. Il ne fait JAMAIS echouer un encaissement. Une messagerie indisponible ne
 *    doit pas empecher le tresorier de prendre l'argent d'une famille : toute
 *    erreur est journalisee et avalee. Le paiement, lui, est deja enregistre.
 *
 * 2. Le fil est cree si besoin, meme sans compte tuteur. La famille qui
 *    obtiendra un acces plus tard y retrouvera tout son historique de recus.
 *
 * 3. Le PDF n'est pas joint : le message porte de quoi le regenerer. Voir la
 *    migration add_piece_jointe_to_messages_table.
 */
class NotificationPaiementService implements NotificationPaiementServiceInterface
{
    /**
     * Sujet du fil dedie aux justificatifs. Sert aussi de cle de recherche :
     * tous les recus d'une famille se rangent dans le meme fil, plutot que
     * d'en ouvrir un par versement.
     */
    private const SUJET_FIL = 'Reçus de paiement';

    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
    ) {}

    /**
     * Previent la famille qu'un versement a ete enregistre.
     *
     * @param array{
     *   libelle: string, montant: int, reste: int, estSolde: bool,
     *   numero: string, type: PieceJointeEnum, pieceId: int
     * } $paiement
     */
    public function notifier(?Eleve $eleve, array $paiement): void
    {
        try {
            $tuteur = $eleve?->tuteur;

            if ($tuteur === null) {
                // Sans tuteur rattache, il n'y a personne a prevenir. Ce n'est
                // pas une anomalie : l'eleve peut etre en cours de saisie.
                return;
            }

            $conversation = $this->filDesRecus($tuteur, $eleve);

            $message = $this->conversations->ajouterMessage($conversation, [
                'expediteur_id'        => null,
                'corps'                => $this->corps($eleve, $paiement),
                // Message de service : il vient de l'etablissement, pas d'un
                // agent en particulier, et ne compte pas comme non-lu pour
                // les agents de la tresorerie.
                'est_systeme'          => true,
                'piece_jointe_type'    => $paiement['type']->value,
                'piece_jointe_id'      => $paiement['pieceId'],
                'piece_jointe_libelle' => $this->libellePiece($paiement),
            ]);

            $conversation = $this->conversations->findById($conversation->id);

            broadcast(new MessageEnvoye($message, $conversation));
        } catch (\Throwable $e) {
            // Voir le principe 1 : l'encaissement prime sur la notification.
            Log::error('Notification de paiement non envoyée', [
                'eleve_id' => $eleve?->id,
                'piece'    => $paiement['type']->value ?? null,
                'piece_id' => $paiement['pieceId'] ?? null,
                'erreur'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Le fil ou atterrissent les justificatifs de cette famille.
     *
     * Un seul fil par tuteur, reutilise a chaque versement : ouvrir une
     * conversation par paiement noierait les echanges reels sous les accuses
     * automatiques.
     *
     * Le fil est rouvert s'il avait ete archive : un nouveau versement le rend
     * de nouveau vivant, et un message dans un fil archive serait invisible.
     */
    private function filDesRecus(Tuteur $tuteur, ?Eleve $eleve): Conversation
    {
        $existant = Conversation::query()
            ->where('tuteur_id', $tuteur->id)
            ->where('service', ServiceDestinataireEnum::TRESORERIE->value)
            ->where('sujet', self::SUJET_FIL)
            ->first();

        if ($existant !== null) {
            if ($existant->statut === StatutConversationEnum::ARCHIVEE) {
                $existant->forceFill([
                    'statut' => StatutConversationEnum::OUVERTE->value,
                ])->save();
            }

            return $existant;
        }

        return $this->conversations->create([
            'tuteur_id'            => $tuteur->id,
            // Le fil couvre toute la fratrie : chaque message nomme l'eleve
            // concerne, et lier le fil a un seul enfant obligerait a en ouvrir
            // un par inscription.
            'eleve_id'             => null,
            'service'              => ServiceDestinataireEnum::TRESORERIE->value,
            'sujet'                => self::SUJET_FIL,
            // OUVERTE et non RESOLUE : la famille peut repondre (contester un
            // montant, demander un echeancier), et la tresorerie doit voir
            // arriver cette reponse dans sa corbeille.
            'statut'               => StatutConversationEnum::OUVERTE->value,
            'derniere_activite_at' => now(),
        ]);
    }

    /**
     * Le texte lu par la famille.
     *
     * Il dit trois choses, dans cet ordre : ce qui a ete paye, pour qui, et ce
     * qu'il reste. Un parent doit pouvoir verifier son echeancier sans ouvrir
     * le PDF.
     */
    private function corps(?Eleve $eleve, array $paiement): string
    {
        $nom     = $eleve?->nom_complet ?? "l'élève";
        $montant = $this->formaterMontant($paiement['montant']);

        $lignes = [];

        $lignes[] = $paiement['estSolde']
            ? "Paiement reçu — {$paiement['libelle']} de {$nom}."
            : "Versement enregistré — {$paiement['libelle']} de {$nom}.";

        $lignes[] = "Montant versé : {$montant} FCFA.";

        if ($paiement['estSolde']) {
            $lignes[] = 'Cette somme solde le montant dû. Merci.';
        } else {
            $reste = $this->formaterMontant($paiement['reste']);
            $lignes[] = "Reste à payer : {$reste} FCFA.";
        }

        $lignes[] = $paiement['estSolde']
            ? "Votre reçu n° {$paiement['numero']} est joint à ce message."
            : "Votre décharge n° {$paiement['numero']} est jointe à ce message.";

        return implode("\n", $lignes);
    }

    /** Ce qui s'affiche sur le bouton de telechargement. */
    private function libellePiece(array $paiement): string
    {
        $nature = $paiement['estSolde'] ? 'Reçu' : 'Décharge';

        return "{$nature} n° {$paiement['numero']}";
    }

    /** 125000 -> « 125 000 » : un montant se lit par tranches de trois. */
    private function formaterMontant(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
