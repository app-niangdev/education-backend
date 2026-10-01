<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreEnseignantRequest;
use App\Http\Requests\UpdateEnseignantRequest;
use App\Interfaces\EnseignantServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnseignantController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly EnseignantServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        $enseignants = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($enseignants, 'Liste des enseignants récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Enseignants récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreEnseignantRequest $request): JsonResponse
    {
        $enseignant = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($enseignant, 'Enseignant créé avec succès', 201);
    }

    public function update(UpdateEnseignantRequest $request, string $id): JsonResponse
    {
        $enseignant = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($enseignant, 'Enseignant modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Enseignant supprimé avec succès');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $enseignant = $this->service->restore($id, $request->user());

        return ApiResponse::success($enseignant, 'Enseignant restauré avec succès');
    }
}
