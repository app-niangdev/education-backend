<?php

namespace App\Http\Controllers;

use App\Enums\StatutContratEnum;
use App\Enums\TypeContratEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\RenouvelerContratRequest;
use App\Http\Requests\ResilierContratRequest;
use App\Http\Requests\StoreContratRequest;
use App\Http\Requests\UpdateContratRequest;
use App\Interfaces\ContratServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les contrats de travail du personnel.
 *
 * Tout est reserve a l'admin et au manager : un contrat porte la remuneration,
 * que ni le surveillant ni le tresorier n'ont a consulter pour leurs collegues.
 */
class ContratController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly ContratServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $contrats = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
            filtres: $request->only(['statut', 'type_contrat', 'contractable_type', 'echeance_dans']),
        );

        return ApiResponse::paginated($contrats, 'Liste des contrats récupérée avec succès');
    }

    /** Les referentiels du module, pour alimenter les listes deroulantes. */
    public function meta(): JsonResponse
    {
        $this->authorizeAdminOrManager();

        return ApiResponse::success([
            'types_contrat' => array_map(
                fn (TypeContratEnum $type) => [
                    'value'          => $type->value,
                    'label'          => $type->libelle(),
                    'exige_date_fin' => $type->exigeDateFin(),
                ],
                TypeContratEnum::cases(),
            ),
            'statuts' => array_map(
                fn (StatutContratEnum $statut) => [
                    'value' => $statut->value,
                    'label' => $statut->libelle(),
                ],
                StatutContratEnum::cases(),
            ),
        ], 'Référentiels des contrats récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        return ApiResponse::success($this->service->find($id));
    }

    /** L'historique contractuel d'un employe : « enseignant », « tresorier »… */
    public function historique(string $type, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        return ApiResponse::success(
            $this->service->historique($type, $id),
            'Historique des contrats récupéré avec succès'
        );
    }

    public function store(StoreContratRequest $request): JsonResponse
    {
        $contrat = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($contrat, 'Contrat créé avec succès', 201);
    }

    public function update(UpdateContratRequest $request, string $id): JsonResponse
    {
        $contrat = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($contrat, 'Contrat modifié avec succès');
    }

    public function resilier(ResilierContratRequest $request, string $id): JsonResponse
    {
        $contrat = $this->service->resilier($id, $request->validated(), $request->user());

        return ApiResponse::success($contrat, 'Contrat résilié avec succès');
    }

    public function renouveler(RenouvelerContratRequest $request, string $id): JsonResponse
    {
        $contrat = $this->service->renouveler($id, $request->validated(), $request->user());

        return ApiResponse::success($contrat, 'Contrat renouvelé avec succès', 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Contrat supprimé avec succès');
    }

    /**
     * Le contrat de travail imprimable, a signer par les deux parties.
     *
     * Il porte un QR code menant a la page publique de verification : le
     * document circule hors du logiciel, un tiers doit pouvoir confirmer qu'il
     * correspond bien a un engagement enregistre.
     */
    public function pdf(string $id): Response
    {
        $this->authorizeAdminOrManager();

        $data = $this->service->dataForPdf($id);

        $pdf = Pdf::loadView('pdf.contrat', $data)->setPaper('a4', 'portrait');

        $numero = $data['contrat']->numero_contrat;

        return $pdf->download('contrat-' . Str::slug($numero) . '.pdf');
    }

    /**
     * Verifie l'authenticite d'un contrat depuis son code, sans authentification.
     *
     * C'est la cible du QR code : la personne qui scanne un contrat papier n'a
     * aucun compte sur l'application. La reponse se limite donc a ce qui atteste
     * le document, la remuneration en etant exclue.
     *
     * Un code inconnu renvoie 404 : c'est la reponse utile, le document presente
     * ne correspond a rien d'enregistre.
     */
    public function verifier(string $code): JsonResponse
    {
        $resume = $this->service->verifierParCode($code);

        if (!$resume) {
            return ApiResponse::error(
                "Aucun contrat ne correspond à ce code de vérification.",
                404
            );
        }

        return ApiResponse::success($resume, 'Contrat authentifié avec succès');
    }
}
