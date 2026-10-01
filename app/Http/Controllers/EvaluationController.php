<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SaveNotesRequest;
use App\Http\Requests\StoreEvaluationRequest;
use App\Http\Requests\UpdateEvaluationRequest;
use App\Interfaces\EvaluationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des évaluations par période et de la saisie des notes.
 *
 * Chaque enseignant crée des évaluations pour ses affectations (couples
 * classe × matière dont il a la charge) et saisit les notes de chaque élève.
 * Un manager/admin dispose d'une vue globale. Le contrôle « propriétaire de
 * l'affectation » est appliqué dans le service.
 */
class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationServiceInterface $service
    ) {}

    /** Les affectations sur lesquelles l'utilisateur peut créer des évaluations. */
    public function mesAffectations(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->affectationsFor($request->user()),
            'Affectations récupérées avec succès'
        );
    }

    /** Métadonnées de saisie : barème par défaut et types d'évaluation. */
    public function meta(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->meta(),
            'Métadonnées des évaluations récupérées avec succès'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $evaluations = $this->service->list(
            authUser:      $request->user(),
            perPage:       (int) $request->input('per_page', 10),
            search:        trim($request->input('search', '')),
            periodeId:     $request->filled('periode_id') ? (int) $request->input('periode_id') : null,
            affectationId: $request->filled('affectation_id') ? (int) $request->input('affectation_id') : null,
        );

        return ApiResponse::paginated($evaluations, 'Liste des évaluations récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->find($request->user(), $id),
            'Évaluation récupérée avec succès'
        );
    }

    /** L'évaluation + la grille des élèves de la classe avec leurs notes. */
    public function gradeSheet(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->gradeSheet($request->user(), $id),
            'Grille de notes récupérée avec succès'
        );
    }

    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $evaluation = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($evaluation, 'Évaluation créée avec succès', 201);
    }

    public function update(UpdateEvaluationRequest $request, string $id): JsonResponse
    {
        $evaluation = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($evaluation, 'Évaluation modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Évaluation supprimée avec succès');
    }

    /** Saisie/mise à jour en lot des notes d'une évaluation. */
    public function saveNotes(SaveNotesRequest $request, string $id): JsonResponse
    {
        $result = $this->service->saveNotes($id, $request->validated()['notes'], $request->user());

        return ApiResponse::success($result, 'Notes enregistrées avec succès');
    }
}
