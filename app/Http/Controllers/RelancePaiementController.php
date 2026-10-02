<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\RelancePaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relances WhatsApp des familles en retard de paiement, depuis l'espace
 * tresorier.
 */
class RelancePaiementController extends Controller
{
    public function __construct(
        private readonly RelancePaiementService $service,
    ) {}

    /** Les familles en retard, et ce qui empeche eventuellement de les relancer. */
    public function debiteurs(Request $request): JsonResponse
    {
        $this->authorizeTresorier($request);

        return ApiResponse::success([
            'debiteurs'    => $this->service->debiteurs(),
            'active'       => $this->service->active(),
            'delai_heures' => $this->service->delaiHeures(),
        ], 'Familles en retard de paiement récupérées avec succès');
    }

    /** Relances et rappels deja envoyes, du plus recent au plus ancien. */
    public function historique(Request $request): JsonResponse
    {
        $this->authorizeTresorier($request);

        $relances = $this->service->historique(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($relances, 'Historique des relances récupéré avec succès');
    }

    /**
     * Relance une famille. 422 = relance sans objet (plus d'arriere, numero
     * inexploitable, deja relancee…) ; 502 = l'envoi a echoue pour ce
     * destinataire ; 503 = le service WhatsApp est en panne, inutile de
     * poursuivre un envoi groupe.
     */
    public function relancer(Request $request, string $tuteurId): JsonResponse
    {
        $this->authorizeTresorier($request);

        $resultat = $this->service->relancer($tuteurId, $request->user());

        return match ($resultat['statut']) {
            'envoyee' => ApiResponse::success($resultat, $resultat['message']),
            'ignoree' => ApiResponse::error($resultat['message'], 422),
            default   => ApiResponse::error($resultat['message'], $resultat['fatal'] ? 503 : 502),
        };
    }

    /**
     * Meme perimetre que les ecrans d'encaissement : le tresorier, et
     * l'administrateur qui supervise l'ensemble.
     */
    private function authorizeTresorier(Request $request): void
    {
        if (! in_array($request->user()?->role?->name, ['treasurer', 'admin'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
