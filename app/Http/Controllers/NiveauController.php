<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreNiveauRequest;
use App\Http\Requests\UpdateNiveauRequest;
use App\Interfaces\NiveauServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NiveauController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly NiveauServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $niveaux = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($niveaux, 'Liste des niveaux récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Niveaux récupérés avec succès');
    }

    /**
     * Les niveaux avec leur barème de l'année en cours : ce que consomme
     * l'interface unifiée Niveaux & frais scolaires.
     */
    public function grille(): JsonResponse
    {
        return ApiResponse::success($this->service->grilleAnneeEnCours(), 'Grille des niveaux récupérée avec succès');
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreNiveauRequest $request): JsonResponse
    {
        $niveau = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($niveau, 'Niveau créé avec succès', 201);
    }

    public function update(UpdateNiveauRequest $request, string $id): JsonResponse
    {
        $niveau = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($niveau, 'Niveau modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Niveau supprimé avec succès');
    }
}
