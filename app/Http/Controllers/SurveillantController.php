<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreSurveillantRequest;
use App\Http\Requests\UpdateSurveillantRequest;
use App\Interfaces\SurveillantServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveillantController extends Controller
{
    public function __construct(
        private readonly SurveillantServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $surveillants = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($surveillants, 'Liste des surveillants récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Surveillants récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreSurveillantRequest $request): JsonResponse
    {
        $surveillant = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($surveillant, 'Surveillant créé avec succès', 201);
    }

    public function update(UpdateSurveillantRequest $request, string $id): JsonResponse
    {
        $surveillant = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($surveillant, 'Surveillant modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Surveillant supprimé avec succès');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $surveillant = $this->service->restore($id, $request->user());

        return ApiResponse::success($surveillant, 'Surveillant restauré avec succès');
    }

    private function authorizeAdminOrManager(): void
    {
        if (!in_array(request()->user()?->role?->name, ['admin', 'manager'])) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
