<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ColorHelper;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\AnnulerInscriptionRequest;
use App\Http\Requests\StoreInscriptionRequest;
use App\Http\Requests\UpdateInscriptionRequest;
use App\Interfaces\InscriptionServiceInterface;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InscriptionController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly InscriptionServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeParcoursInscription();

        $inscriptions = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
            filters: $request->only([
                'matricule',
                'numero_inscription',
                'classe_id',
                'statut_inscription',
                'statut_paiement',
                'type_inscription',
            ]),
        );

        return ApiResponse::paginated($inscriptions, 'Liste des inscriptions récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Inscriptions récupérées avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeParcoursInscription();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreInscriptionRequest $request): JsonResponse
    {
        $inscription = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($inscription, 'Inscription créée avec succès', 201);
    }

    public function update(UpdateInscriptionRequest $request, string $id): JsonResponse
    {
        $inscription = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($inscription, 'Inscription modifiée avec succès');
    }

    public function annuler(AnnulerInscriptionRequest $request, string $id): JsonResponse
    {
        $inscription = $this->service->annuler($id, $request->validated()['motif'] ?? null, $request->user());

        return ApiResponse::success($inscription, 'Inscription annulée avec succès');
    }

    /**
     * Le role tranche large (admin/manager/surveillant), la portee fine —
     * quelles inscriptions chacun peut supprimer — est departagee dans le
     * service, qui seul a l'inscription chargee pour verifier son statut et
     * son createur.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Inscription supprimée avec succès');
    }

    /** Genere la fiche de renseignement (frais + echeancier) en PDF. */
    public function fichePdf(string $id): Response
    {
        $this->authorizeParcoursInscription();

        $data                  = $this->service->dataForFichePdf($id);
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $pdf = Pdf::loadView('pdf.fiche-renseignement', $data)
            ->setPaper('a4', 'portrait');

        $numero   = $data['inscription']->numero_inscription;
        $filename = 'fiche-renseignement-' . Str::slug($numero) . '.pdf';

        return $pdf->download($filename);
    }
}
