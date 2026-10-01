<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StorePeriodeRequest;
use App\Http\Requests\UpdatePeriodeRequest;
use App\Interfaces\PeriodeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeController extends Controller
{
    public function __construct(
        private readonly PeriodeServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $periodes = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
            anneeId: $request->integer('annee_scolaire_id') ?: null,
        );

        return ApiResponse::paginated($periodes, 'Liste des périodes récupérée avec succès');
    }

    public function byAnnee(string $anneeId): JsonResponse
    {
        $periodes = $this->service->allByAnnee((int) $anneeId);

        return ApiResponse::success($periodes, 'Périodes récupérées avec succès');
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->service->find($id));
    }

    public function store(StorePeriodeRequest $request): JsonResponse
    {
        $periode = $this->service->create($request->validated());

        return ApiResponse::success($periode, 'Période créée avec succès', 201);
    }

    public function update(UpdatePeriodeRequest $request, string $id): JsonResponse
    {
        $periode = $this->service->update($id, $request->validated());

        return ApiResponse::success($periode, 'Période modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $this->service->delete($id);

        return ApiResponse::success(null, 'Période supprimée avec succès');
    }

    private function authorizeAdminOrManager(Request $request): void
    {
        if (!in_array($request->user()?->role?->name, ['admin', 'manager'])) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
