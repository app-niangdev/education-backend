<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreTresorierRequest;
use App\Http\Requests\UpdateTresorierRequest;
use App\Interfaces\TresorierServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TresorierController extends Controller
{
    public function __construct(
        private readonly TresorierServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $tresoriers = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($tresoriers, 'Liste des trésoriers récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Trésoriers récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreTresorierRequest $request): JsonResponse
    {
        $tresorier = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($tresorier, 'Trésorier créé avec succès', 201);
    }

    public function update(UpdateTresorierRequest $request, string $id): JsonResponse
    {
        $tresorier = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($tresorier, 'Trésorier modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Trésorier supprimé avec succès');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $tresorier = $this->service->restore($id, $request->user());

        return ApiResponse::success($tresorier, 'Trésorier restauré avec succès');
    }

    private function authorizeAdminOrManager(): void
    {
        if (!in_array(request()->user()?->role?->name, ['admin', 'manager'])) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
