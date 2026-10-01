<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreFraisScolaireRequest;
use App\Http\Requests\UpdateFraisScolaireRequest;
use App\Interfaces\FraisScolaireServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FraisScolaireController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly FraisScolaireServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $frais = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($frais, 'Liste des frais scolaires récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Frais scolaires récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->service->find($id));
    }

    /**
     * Grille tarifaire de l'annee scolaire en cours, avec le niveau de chaque
     * bareme : c'est ce qu'affiche la page d'accueil publique.
     *
     * Route non authentifiee : elle n'expose que des tarifs, deja publics.
     */
    public function listePublique(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->grilleAnneeEnCours(),
            'Grille tarifaire récupérée avec succès',
        );
    }

    /**
     * Referentiels du formulaire : l'annee scolaire sur laquelle le bareme se
     * cree (celle en cours) et les niveaux qui n'y ont pas encore de bareme.
     * `frais_id` bascule sur le contexte du bareme edite.
     */
    public function referentiels(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        return ApiResponse::success(
            $this->service->referentielsFormulaire($request->input('frais_id')),
            'Référentiels récupérés avec succès',
        );
    }

    public function store(StoreFraisScolaireRequest $request): JsonResponse
    {
        $frais = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($frais, 'Barème créé avec succès', 201);
    }

    public function update(UpdateFraisScolaireRequest $request, string $id): JsonResponse
    {
        $frais = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($frais, 'Barème modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Barème supprimé avec succès');
    }

    /**
     * Genere la grille tarifaire d'une annee depuis celle de la precedente.
     * Idempotent : rejouer l'appel ne cree que les baremes encore manquants.
     */
    public function genererGrille(Request $request, string $anneeScolaireId): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $nbCrees = $this->service->genererGrille($anneeScolaireId, $request->user());

        $message = $nbCrees > 0
            ? "{$nbCrees} barème(s) généré(s) avec succès"
            : 'Tous les barèmes existaient déjà pour cette année';

        return ApiResponse::success(['crees' => $nbCrees], $message, $nbCrees > 0 ? 201 : 200);
    }
}
