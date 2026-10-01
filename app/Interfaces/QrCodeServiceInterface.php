<?php

namespace App\Interfaces;

interface QrCodeServiceInterface
{
    /**
     * Le QR code encodant `$contenu`, sous forme de data URI PNG directement
     * utilisable dans un `<img src>` de template PDF.
     *
     * Retourne null si le contenu est vide ou si la generation echoue : au
     * document de prevoir un repli lisible.
     */
    public function dataUri(string $contenu, int $taille = 220): ?string;
}
