<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Interfaces\StatistiqueServiceInterface;
use App\Models\AnneeScolaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistiques du tableau de bord manager : effectifs, répartitions des élèves,
 * état des inscriptions et indicateurs financiers de l'année scolaire.
 * Réservé à l'admin et au manager.
 */
class StatistiqueController extends Controller
{
    public function __construct(
        private readonly StatistiqueServiceInterface $service
    ) {}

    /**
     * Tableau de bord de l'année scolaire ciblée (annee_scolaire_id en query),
     * ou de l'année en cours par défaut.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $annee = null;

        if ($request->filled('annee_scolaire_id')) {
            $annee = AnneeScolaire::findOrFail($request->input('annee_scolaire_id'));
        }

        return ApiResponse::success(
            $this->service->dashboard($annee),
            'Statistiques récupérées avec succès'
        );
    }

    /**
     * Tableau de bord financier du trésorier (recouvrement, encaissements,
     * modes de paiement, mensualités, restes). Réservé au trésorier ; l'admin
     * et le manager y ont aussi accès pour supervision.
     */
    public function dashboardTresorier(Request $request): JsonResponse
    {
        if (!in_array($request->user()?->role?->name, ['treasurer', 'admin', 'manager'])) {
            abort(403, 'Accès non autorisé.');
        }

        $annee = null;

        if ($request->filled('annee_scolaire_id')) {
            $annee = AnneeScolaire::findOrFail($request->input('annee_scolaire_id'));
        }

        return ApiResponse::success(
            $this->service->dashboardTresorier($annee),
            'Statistiques du trésorier récupérées avec succès'
        );
    }

    /**
     * Tableau de bord de l'enseignant connecté : affectations, évaluations et
     * taux de saisie des notes. Réservé aux enseignants.
     */
    public function dashboardEnseignant(Request $request): JsonResponse
    {
        if ($request->user()?->role?->name !== 'teacher') {
            abort(403, 'Accès non autorisé.');
        }

        $annee = null;

        if ($request->filled('annee_scolaire_id')) {
            $annee = AnneeScolaire::findOrFail($request->input('annee_scolaire_id'));
        }

        return ApiResponse::success(
            $this->service->dashboardEnseignant($request->user(), $annee),
            'Statistiques de l\'enseignant récupérées avec succès'
        );
    }

    private function authorizeAdminOrManager(Request $request): void
    {
        if (!in_array($request->user()?->role?->name, ['admin', 'manager'])) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
