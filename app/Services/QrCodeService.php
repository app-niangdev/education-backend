<?php

namespace App\Services;

use App\Interfaces\QrCodeServiceInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;

/**
 * La fabrication des QR codes destines aux documents imprimes.
 *
 * Le rendu se fait en PNG encode dans l'URL elle-meme : DomPDF n'ouvre aucune
 * connexion pour resoudre une image distante, et un fichier temporaire
 * imposerait un nettoyage dont personne ne serait responsable.
 */
class QrCodeService implements QrCodeServiceInterface
{
    public function dataUri(string $contenu, int $taille = 220): ?string
    {
        if (trim($contenu) === '') {
            return null;
        }

        try {
            return (new Builder(writer: new PngWriter()))
                ->build(
                    data: $contenu,
                    // Un contrat est un papier : il se plie, se tache et se
                    // photocopie. Le niveau Medium laisse le code lisible
                    // meme partiellement abime.
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: $taille,
                    margin: 8,
                )
                ->getDataUri();
        } catch (\Throwable $e) {
            // Un QR manquant ne doit pas priver l'etablissement de son contrat :
            // le PDF sort sans, en affichant l'URL de verification en clair.
            Log::warning('Génération du QR code impossible', [
                'contenu' => $contenu,
                'erreur'  => $e->getMessage(),
            ]);

            return null;
        }
    }
}
