<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreMatiereRequest;
use App\Http\Requests\UpdateMatiereRequest;
use App\Interfaces\MatiereServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatiereController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly MatiereServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        $matieres = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($matieres, 'Liste des matières récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Matières récupérées avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $matiere = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($matiere, 'Matière créée avec succès', 201);
    }

    public function update(UpdateMatiereRequest $request, string $id): JsonResponse
    {
        $matiere = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($matiere, 'Matière modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Matière supprimée avec succès');
    }
}
