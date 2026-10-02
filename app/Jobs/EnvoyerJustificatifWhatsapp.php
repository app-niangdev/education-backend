<?php

namespace App\Jobs;

use App\Enums\PieceJointeEnum;
use App\Models\Eleve;
use App\Services\WhatsappJustificatifService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Envoi WhatsApp du justificatif d'un encaissement, execute apres la reponse
 * HTTP : le tresorier n'attend pas WAHA pour voir son paiement enregistre.
 */
class EnvoyerJustificatifWhatsapp
{
    use Dispatchable;

    /**
     * @param array{libelle: string, montant: int, reste: int, estSolde: bool, numero: string} $paiement
     */
    public function __construct(
        public int $eleveId,
        public PieceJointeEnum $type,
        public int $pieceId,
        public array $paiement,
    ) {}

    public function handle(WhatsappJustificatifService $service): void
    {
        $service->envoyer(
            Eleve::with('tuteur')->find($this->eleveId),
            $this->type,
            $this->pieceId,
            $this->paiement,
        );
    }
}
