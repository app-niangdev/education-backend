<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreClasseRequest;
use App\Http\Requests\UpdateClasseRequest;
use App\Interfaces\ClasseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly ClasseServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        $classes = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($classes, 'Liste des classes récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Classes récupérées avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreClasseRequest $request): JsonResponse
    {
        $classe = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($classe, 'Classe créée avec succès', 201);
    }

    public function update(UpdateClasseRequest $request, string $id): JsonResponse
    {
        $classe = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($classe, 'Classe modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Classe supprimée avec succès');
    }
}
