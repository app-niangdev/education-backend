<?php

namespace App\Interfaces;

use App\Enums\PieceJointeEnum;
use Illuminate\Http\Response;

interface JustificatifServiceInterface
{
    /**
     * Regenere un justificatif de paiement en PDF.
     *
     * Le fichier n'est jamais stocke : il est reconstruit depuis les donnees
     * du paiement a chaque demande. Le controle d'acces ne se fait pas ici
     * mais chez l'appelant, qui seul connait le contexte (fil de discussion,
     * module finance).
     */
    public function rendre(PieceJointeEnum $type, int|string $pieceId): Response;

    /**
     * Le meme justificatif, sous forme de fichier a transmettre plutot que de
     * reponse HTTP : c'est ce qui part au tuteur sur WhatsApp.
     *
     * @return array{contenu: string, nom: string}
     */
    public function fichier(PieceJointeEnum $type, int|string $pieceId): array;
}
