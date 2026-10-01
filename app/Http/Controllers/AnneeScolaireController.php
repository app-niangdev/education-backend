<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreAnneeScolaireRequest;
use App\Http\Requests\UpdateAnneeScolaireRequest;
use App\Interfaces\AnneeScolaireServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnneeScolaireController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly AnneeScolaireServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $annees = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($annees, 'Liste des années scolaires récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Années scolaires récupérées avec succès');
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreAnneeScolaireRequest $request): JsonResponse
    {
        $annee = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($annee, 'Année scolaire créée avec succès', 201);
    }

    public function update(UpdateAnneeScolaireRequest $request, string $id): JsonResponse
    {
        $annee = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($annee, 'Année scolaire modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        // Suppression reservee a l'admin : le manager cree et modifie les
        // annees, mais supprimer celle qui encadre bulletins, inscriptions et
        // paiements n'est pas de la gestion courante.
        $this->authorizeAdmin($request);

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Année scolaire supprimée avec succès');
    }
}
