<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ColorHelper;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\GenererBulletinsRequest;
use App\Http\Requests\PublierBulletinsRequest;
use App\Http\Requests\UpdateConseilClasseRequest;
use App\Interfaces\BulletinServiceInterface;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulletins de notes : génération par classe, conseil de classe, publication
 * et impression.
 *
 * Les écritures sont réservées à l'admin et au manager, contrôle porté par les
 * FormRequests. La lecture est délégée au service, qui exclut le trésorier et
 * borne l'enseignant à ses propres classes — un contrôle par rôle au niveau du
 * contrôleur ne saurait pas exprimer cette nuance.
 */
class BulletinController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly BulletinServiceInterface $service
    ) {}

    /** Référentiels de saisie : statuts, mentions, décisions, distinctions. */
    public function meta(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->meta(),
            'Métadonnées des bulletins récupérées avec succès'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $bulletins = $this->service->list(
            authUser:  $request->user(),
            perPage:   (int) $request->input('per_page', 10),
            search:    trim($request->input('search', '')),
            classeId:  $request->filled('classe_id') ? (int) $request->input('classe_id') : null,
            periodeId: $request->filled('periode_id') ? (int) $request->input('periode_id') : null,
            statut:    $request->filled('statut') ? $request->input('statut') : null,
        );

        return ApiResponse::paginated($bulletins, 'Liste des bulletins récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->find($request->user(), $id),
            'Bulletin récupéré avec succès'
        );
    }

    /** Génère ou recalcule les brouillons de toute une classe. */
    public function generer(GenererBulletinsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $resultat = $this->service->genererPourClasse(
            $data['classe_id'],
            $data['periode_id'],
            $request->user(),
        );

        return ApiResponse::success($resultat, 'Bulletins générés avec succès', 201);
    }

    public function updateConseil(UpdateConseilClasseRequest $request, string $id): JsonResponse
    {
        $bulletin = $this->service->updateConseil($id, $request->validated(), $request->user());

        return ApiResponse::success($bulletin, 'Conseil de classe enregistré avec succès');
    }

    public function publierClasse(PublierBulletinsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $nombre = $this->service->publierClasse(
            $data['classe_id'],
            $data['periode_id'],
            $request->user(),
        );

        return ApiResponse::success(
            ['publies' => $nombre],
            "{$nombre} bulletin(s) publié(s) avec succès"
        );
    }

    public function publierUn(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        return ApiResponse::success(
            $this->service->publierUn($id, $request->user()),
            'Bulletin publié avec succès'
        );
    }

    public function depublierClasse(PublierBulletinsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $nombre = $this->service->depublierClasse(
            $data['classe_id'],
            $data['periode_id'],
            $request->user(),
        );

        return ApiResponse::success(
            ['depublies' => $nombre],
            "{$nombre} bulletin(s) dépublié(s) avec succès"
        );
    }

    public function depublierUn(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        return ApiResponse::success(
            $this->service->depublierUn($id, $request->user()),
            'Bulletin dépublié avec succès'
        );
    }

    /** Génère et télécharge le bulletin d'un élève en PDF. */
    public function pdf(Request $request, string $id): Response
    {
        $data                  = $this->service->dataForPdf($request->user(), $id);
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $bulletin = $data['bulletin'];

        $pdf = Pdf::loadView('pdf.bulletin', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'bulletin-'
            . Str::slug($bulletin->nom_complet) . '-'
            . Str::slug($bulletin->periode?->libelle ?? 'periode') . '.pdf';

        return $pdf->download($filename);
    }

    /** Génère et télécharge les bulletins de toute une classe, une page par élève. */
    public function pdfClasse(Request $request): Response
    {
        $request->validate([
            'classe_id'  => ['required', 'integer', 'exists:classes,id'],
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
        ]);

        $data = $this->service->dataForPdfClasse(
            $request->user(),
            (int) $request->input('classe_id'),
            (int) $request->input('periode_id'),
        );

        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $pdf = Pdf::loadView('pdf.bulletins-classe', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'bulletins-'
            . Str::slug($data['classe']->nom) . '-'
            . Str::slug($data['periode']->libelle) . '.pdf';

        return $pdf->download($filename);
    }
}
