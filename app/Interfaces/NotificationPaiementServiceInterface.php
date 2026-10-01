<?php

namespace App\Interfaces;

use App\Enums\PieceJointeEnum;
use App\Models\Eleve;

interface NotificationPaiementServiceInterface
{
    /**
     * Depose l'accuse de paiement dans la messagerie de la famille.
     *
     * N'echoue jamais : une messagerie indisponible ne doit pas empecher un
     * encaissement. Les erreurs sont journalisees et avalees.
     *
     * @param array{
     *   libelle: string,
     *   montant: int,
     *   reste: int,
     *   estSolde: bool,
     *   numero: string,
     *   type: PieceJointeEnum,
     *   pieceId: int
     * } $paiement
     */
    public function notifier(?Eleve $eleve, array $paiement): void;
}
