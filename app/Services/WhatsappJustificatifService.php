<?php

namespace App\Services;

use App\Enums\PieceJointeEnum;
use App\Interfaces\FinanceTresorierServiceInterface;
use App\Interfaces\JustificatifServiceInterface;
use App\Models\Eleve;
use App\Models\Etablissement;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Envoi au tuteur, sur WhatsApp, du justificatif d'un encaissement : recu
 * d'inscription, recu de mensualite ou facture multi-mois.
 *
 * Deux chemins, un seul document :
 *
 *  - automatique, apres chaque encaissement (EnvoyerJustificatifWhatsapp) :
 *    un echec est journalise et avale, il ne remet jamais le paiement en cause ;
 *  - a la demande du tresorier (bouton « Renvoyer ») : l'echec remonte, pour
 *    qu'il sache que la famille n'a rien recu.
 *
 * Le PDF est celui de la caisse et de la messagerie (JustificatifService) :
 * la famille ne recoit pas un document different selon le canal.
 */
class WhatsappJustificatifService
{
    public function __construct(
        private readonly WahaService                      $waha,
        private readonly JustificatifServiceInterface     $justificatifs,
        private readonly FinanceTresorierServiceInterface $finance,
    ) {}

    /** L'envoi est-il possible pour cet eleve (WAHA configure, tuteur joignable) ? */
    public function disponible(?Eleve $eleve): bool
    {
        return $this->destinataire($eleve) !== null;
    }

    /**
     * Envoi en tache de fond : un echec est seulement journalise.
     *
     * @param array{libelle: string, montant: int, reste: int, estSolde: bool, numero: string} $paiement
     */
    public function envoyer(?Eleve $eleve, PieceJointeEnum $type, int $pieceId, array $paiement): void
    {
        $chatId = $this->destinataire($eleve);

        if ($chatId === null) {
            return;
        }

        try {
            $this->transmettre($chatId, $eleve, $type, $pieceId, $paiement);
        } catch (Throwable $e) {
            Log::warning('Envoi WhatsApp du justificatif impossible', [
                'eleve_id' => $eleve?->id,
                'piece'    => $type->value,
                'piece_id' => $pieceId,
                'erreur'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Renvoi immediat d'un justificatif deja emis. Le message est recompose
     * depuis le paiement lui-meme : il decrit la situation du jour, pas celle
     * de l'encaissement.
     *
     * @throws Throwable si l'envoi ne s'applique pas, ou si WAHA le refuse.
     */
    public function renvoyer(PieceJointeEnum $type, int|string $pieceId): void
    {
        $data = match ($type) {
            PieceJointeEnum::RECU_INSCRIPTION    => $this->finance->dataForRecuInscription($pieceId),
            PieceJointeEnum::RECU_MENSUALITE     => $this->finance->dataForRecuMensualite($pieceId),
            PieceJointeEnum::FACTURE_MENSUALITES => $this->finance->dataForFactureMensualite($pieceId),
        };

        $eleve  = $data['eleve'] ?? null;
        $chatId = $this->destinataire($eleve)
            ?? throw new RuntimeException($this->motifIndisponible($eleve));

        $facture = $data['facture'] ?? null;

        $this->transmettre($chatId, $eleve, $type, (int) $pieceId, [
            'libelle'  => $facture
                ? sprintf('%d mensualité%s', count($data['lignes']), count($data['lignes']) > 1 ? 's' : '')
                : $data['libelleObjet'],
            'montant'  => (int) ($facture?->montant_total ?? $data['paiement']->montant),
            'reste'    => (int) $data['reste'],
            'estSolde' => (bool) $data['estSolde'],
            'numero'   => $facture?->numero_facture ?? $data['paiement']->numero_recu,
        ]);
    }

    /** Ce qui empeche l'envoi, dit de facon a ce que le tresorier sache quoi corriger. */
    public function motifIndisponible(?Eleve $eleve): string
    {
        return match (true) {
            !$this->waha->isConfigured()
                => "L'envoi WhatsApp n'est pas configuré pour cet établissement.",
            $eleve?->tuteur === null
                => "Cet élève n'a pas de tuteur rattaché.",
            default
                => "Le téléphone du tuteur n'est pas un numéro WhatsApp valide.",
        };
    }

    private function transmettre(string $chatId, ?Eleve $eleve, PieceJointeEnum $type, int $pieceId, array $paiement): void
    {
        $fichier = $this->justificatifs->fichier($type, $pieceId);

        $this->waha->sendFile(
            $chatId,
            $fichier['contenu'],
            $fichier['nom'],
            'application/pdf',
            $this->legende($eleve, $paiement),
        );
    }

    /** Destinataire WAHA, ou null si l'envoi ne s'applique pas. */
    private function destinataire(?Eleve $eleve): ?string
    {
        if (!$this->waha->isConfigured()) {
            return null;
        }

        return $this->waha->chatId($eleve?->tuteur?->telephone_principal);
    }

    /**
     * Le texte qui accompagne le PDF. Il dit ce qui a ete paye, pour qui, et
     * ce qu'il reste : un parent doit pouvoir suivre son echeancier sans
     * ouvrir la piece jointe.
     */
    private function legende(?Eleve $eleve, array $paiement): string
    {
        $tuteur  = $eleve?->tuteur;
        $ecole   = Etablissement::first()?->nom;
        $nom     = $eleve?->nom_complet ?? "l'élève";
        $montant = number_format($paiement['montant'], 0, ',', ' ');
        $piece   = $paiement['estSolde']
            ? "Votre reçu n° {$paiement['numero']} est joint"
            : "Votre décharge n° {$paiement['numero']} est jointe";

        $lignes = [
            'Bonjour' . ($tuteur ? ' ' . trim("{$tuteur->prenom} {$tuteur->nom}") : '') . ',',
            '',
            "Nous avons bien reçu votre paiement de *{$montant} FCFA*"
                . ($ecole ? " à *{$ecole}*" : '')
                . " — {$paiement['libelle']} de {$nom}.",
            $paiement['estSolde']
                ? 'Cette somme solde le montant dû.'
                : 'Reste à payer : *' . number_format($paiement['reste'], 0, ',', ' ') . ' FCFA*.',
            '',
            "{$piece} à ce message. Merci de votre confiance.",
        ];

        return implode("\n", $lignes);
    }
}
