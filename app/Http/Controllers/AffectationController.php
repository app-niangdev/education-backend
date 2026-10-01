<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreAffectationRequest;
use App\Http\Requests\UpdateAffectationRequest;
use App\Interfaces\AffectationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des affectations : rattacher un enseignant à un couple
 * (classe × matière). Un enseignant peut être affecté à plusieurs matières.
 */
class AffectationController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly AffectationServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        $affectations = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($affectations, 'Liste des affectations récupérée avec succès');
    }

    public function byEnseignant(string $enseignantId): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success(
            $this->service->forEnseignant($enseignantId),
            "Affectations de l'enseignant récupérées avec succès"
        );
    }

    public function byClasse(string $classeId): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success(
            $this->service->forClasse($classeId),
            'Affectations de la classe récupérées avec succès'
        );
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreAffectationRequest $request): JsonResponse
    {
        $affectation = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($affectation, 'Enseignant affecté avec succès', 201);
    }

    public function update(UpdateAffectationRequest $request, string $id): JsonResponse
    {
        $affectation = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($affectation, 'Affectation modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Affectation supprimée avec succès');
    }
}
