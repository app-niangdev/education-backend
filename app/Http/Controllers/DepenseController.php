<?php

namespace App\Http\Controllers;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use App\Enums\StatutDepenseEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\FiltreDepenseRequest;
use App\Http\Requests\RefuserDepenseRequest;
use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Interfaces\DepenseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les depenses de l'etablissement.
 *
 * Tout le module est ouvert a l'admin, au manager et au tresorier ; la
 * suppression reste reservee a l'admin et au manager, comme partout ailleurs
 * dans l'application.
 *
 * La saisie et la validation sont deux actes distincts : le tresorier
 * enregistre la depense, qui reste EN_ATTENTE, et seul un manager (ou l'admin)
 * l'engage en la validant. Tant qu'elle ne l'est pas, elle ne pese ni sur les
 * totaux ni sur le bilan.
 */
class DepenseController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly DepenseServiceInterface $service
    ) {}

    public function index(FiltreDepenseRequest $request): JsonResponse
    {
        $depenses = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            filters: $request->filtres(),
        );

        return ApiResponse::paginated($depenses, 'Liste des dépenses récupérée avec succès');
    }

    public function all(FiltreDepenseRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->all($request->filtres()),
            'Dépenses récupérées avec succès',
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrTreasurer($request);

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreDepenseRequest $request): JsonResponse
    {
        $depense = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($depense, 'Dépense enregistrée avec succès', 201);
    }

    public function update(UpdateDepenseRequest $request, string $id): JsonResponse
    {
        $depense = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($depense, 'Dépense modifiée avec succès');
    }

    /**
     * Valide la depense : le manager engage l'etablissement, et la depense
     * entre alors dans les totaux et le bilan.
     *
     * Reserve a l'admin et au manager — le tresorier saisit, il ne s'autorise
     * pas lui-meme. C'est la separation qui donne son sens au circuit.
     */
    public function valider(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $depense = $this->service->valider($id, $request->user());

        return ApiResponse::success($depense, 'Dépense validée avec succès');
    }

    /**
     * Refuse la depense, avec un motif conserve sur la fiche.
     *
     * L'autorisation est portee par RefuserDepenseRequest, qui valide aussi le
     * motif : le refus est refuse si l'explication manque.
     */
    public function refuser(RefuserDepenseRequest $request, string $id): JsonResponse
    {
        $depense = $this->service->refuser(
            $id,
            $request->validated()['motif_refus'],
            $request->user(),
        );

        return ApiResponse::success($depense, 'Dépense refusée');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        // Suppression : admin et manager uniquement.
        $this->authorizeAdminOrManager($request);

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Dépense supprimée avec succès');
    }

    /**
     * Totaux du peuplement filtre : montant global et repartition par poste.
     * Repond aux memes filtres que la liste, afin que les totaux affiches
     * correspondent toujours a ce que l'utilisateur a sous les yeux.
     */
    public function totaux(FiltreDepenseRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->totaux($request->filtres()),
            'Totaux des dépenses récupérés avec succès',
        );
    }

    /** Les mois de l'annee scolaire en cours, pour alimenter le filtre mensuel. */
    public function moisAnneeEnCours(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrTreasurer($request);

        return ApiResponse::success(
            $this->service->moisAnneeEnCours(),
            'Mois de l\'année scolaire en cours récupérés avec succès',
        );
    }

    /** Referentiels de saisie : categories et modes de paiement. */
    public function referentiels(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrTreasurer($request);

        return ApiResponse::success([
            'categories' => array_map(
                fn (CategorieDepenseEnum $c) => ['valeur' => $c->value, 'libelle' => $c->libelle()],
                CategorieDepenseEnum::cases(),
            ),
            'modes_paiement' => array_map(
                fn (ModePaiementEnum $m) => ['valeur' => $m->value, 'libelle' => $m->libelle()],
                ModePaiementEnum::cases(),
            ),
            'statuts' => array_map(
                fn (StatutDepenseEnum $s) => ['valeur' => $s->value, 'libelle' => $s->libelle()],
                StatutDepenseEnum::cases(),
            ),
        ], 'Référentiels des dépenses récupérés avec succès');
    }
}
