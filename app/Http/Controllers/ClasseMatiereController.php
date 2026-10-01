<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreClasseMatiereRequest;
use App\Http\Requests\UpdateClasseMatiereRequest;
use App\Interfaces\ClasseMatiereServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion du programme d'une classe : quelles matières y sont enseignées
 * et avec quel coefficient / volume horaire (propres à la classe).
 */
class ClasseMatiereController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly ClasseMatiereServiceInterface $service
    ) {}

    public function byClasse(string $classeId): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success(
            $this->service->forClasse($classeId),
            'Programme de la classe récupéré avec succès'
        );
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreClasseMatiereRequest $request): JsonResponse
    {
        $classeMatiere = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($classeMatiere, 'Matière ajoutée au programme avec succès', 201);
    }

    public function update(UpdateClasseMatiereRequest $request, string $id): JsonResponse
    {
        $classeMatiere = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($classeMatiere, 'Coefficient modifié avec succès');
    }

    /** Fixe l'ordre des matières, celui que suivra le bulletin. */
    public function reordonner(Request $request, string $classeId): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:classe_matiere,id'],
        ], [
            'ids.required' => "L'ordre des matières est obligatoire.",
            'ids.*.exists' => 'Une des matières sélectionnées est invalide.',
        ]);

        return ApiResponse::success(
            $this->service->reordonner($classeId, $data['ids'], $request->user()),
            'Ordre des matières enregistré avec succès'
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Matière retirée du programme avec succès');
    }
}
