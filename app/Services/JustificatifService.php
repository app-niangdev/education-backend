<?php

namespace App\Services;

use App\Enums\PieceJointeEnum;
use App\Helpers\ColorHelper;
use App\Interfaces\FinanceTresorierServiceInterface;
use App\Interfaces\JustificatifServiceInterface;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Fabrique les justificatifs de paiement en PDF.
 *
 * Le meme document est servi a deux endroits : au tresorier depuis le module
 * finance, et a la famille depuis sa messagerie. Le rendu vit donc ici plutot
 * que dans un controleur, sans quoi les deux chemins finiraient par diverger —
 * un recu imprime a la caisse ne doit pas differer de celui recu par le parent.
 *
 * Aucun fichier n'est ecrit sur le disque : le PDF est reconstruit a chaque
 * demande depuis les donnees du paiement. Voir la migration
 * add_piece_jointe_to_messages_table.
 */
class JustificatifService implements JustificatifServiceInterface
{
    public function __construct(
        private readonly FinanceTresorierServiceInterface $finance,
    ) {}

    public function rendre(PieceJointeEnum $type, int|string $pieceId): Response
    {
        return match ($type) {
            PieceJointeEnum::RECU_INSCRIPTION => $this->recu(
                $this->finance->dataForRecuInscription($pieceId)
            ),
            PieceJointeEnum::RECU_MENSUALITE => $this->recu(
                $this->finance->dataForRecuMensualite($pieceId)
            ),
            PieceJointeEnum::FACTURE_MENSUALITES => $this->facture(
                $this->finance->dataForFactureMensualite($pieceId)
            ),
        };
    }

    /**
     * Recu ou decharge, selon que le versement solde ou non le montant du.
     * Le nom du fichier le dit : une famille qui archive ses documents doit
     * distinguer un acompte d'un solde sans les ouvrir.
     */
    private function recu(array $data): Response
    {
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $prefixe = $data['estSolde'] ? 'recu' : 'decharge';

        return Pdf::loadView('pdf.recu-paiement', $data)
            ->setPaper('a4', 'portrait')
            ->download($prefixe . '-' . Str::slug($data['paiement']->numero_recu) . '.pdf');
    }

    private function facture(array $data): Response
    {
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $prefixe = $data['estSolde'] ? 'facture' : 'decharge';

        return Pdf::loadView('pdf.facture-mensualites', $data)
            ->setPaper('a4', 'portrait')
            ->download($prefixe . '-' . Str::slug($data['facture']->numero_facture) . '.pdf');
    }
}
