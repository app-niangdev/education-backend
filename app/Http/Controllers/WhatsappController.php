<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Services\WahaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Etat de la liaison WhatsApp de l'etablissement.
 *
 * Les recus et les relances partent en silence : quand la session se
 * deconnecte (telephone eteint, QR code a rescanner), rien ne le signale
 * avant qu'une famille ne se plaigne. Ce voyant le rend visible a l'admin et
 * au manager.
 */
class WhatsappController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly WahaService $waha,
    ) {}

    public function statut(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        return ApiResponse::success($this->etat());
    }

    /**
     * @return array{etat: 'connecte'|'deconnecte'|'injoignable'|'non_configure', message: string, numero: ?string}
     */
    private function etat(): array
    {
        if (!$this->waha->isConfigured()) {
            return $this->resultat('non_configure', "L'envoi WhatsApp n'est pas configuré : aucun reçu ni relance ne part.");
        }

        try {
            $session = $this->waha->sessionStatus();
        } catch (RequestException $e) {
            Log::warning('Etat de la session WhatsApp illisible', ['erreur' => $e->getMessage()]);

            return match ($e->response->status()) {
                401, 403 => $this->resultat('injoignable', 'Le service WhatsApp refuse la clé configurée.'),
                404      => $this->resultat('deconnecte', "La session WhatsApp configurée n'existe pas."),
                default  => $this->resultat('injoignable', 'Le service WhatsApp a répondu par une erreur.'),
            };
        } catch (\Throwable $e) {
            Log::warning('Etat de la session WhatsApp illisible', ['erreur' => $e->getMessage()]);

            return $this->resultat('injoignable', 'Le service WhatsApp ne répond pas.');
        }

        return match ($session['status']) {
            'WORKING'      => $this->resultat('connecte', 'WhatsApp est connecté : les reçus et les relances partent.', $session['numero']),
            'SCAN_QR_CODE' => $this->resultat('deconnecte', 'WhatsApp est déconnecté : le QR code doit être rescanné.'),
            'STARTING'     => $this->resultat('deconnecte', 'La session WhatsApp est en cours de démarrage.'),
            default        => $this->resultat('deconnecte', "La session WhatsApp est arrêtée : aucun message ne part."),
        };
    }

    private function resultat(string $etat, string $message, ?string $numero = null): array
    {
        return ['etat' => $etat, 'message' => $message, 'numero' => $numero];
    }
}
