<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ColorHelper;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\StoreEmploiDuTempsRequest;
use App\Http\Requests\UpdateEmploiDuTempsRequest;
use App\Interfaces\EmploiDuTempsServiceInterface;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EmploiDuTempsController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly EmploiDuTempsServiceInterface $service
    ) {}

    public function byClasse(string $classeId): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success(
            $this->service->forClasse($classeId),
            "Emploi du temps de la classe récupéré avec succès"
        );
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreEmploiDuTempsRequest $request): JsonResponse
    {
        $creneau = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($creneau, 'Créneau ajouté avec succès', 201);
    }

    public function update(UpdateEmploiDuTempsRequest $request, string $id): JsonResponse
    {
        $creneau = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($creneau, 'Créneau modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Créneau supprimé avec succès');
    }

    /** Genere et telecharge l'emploi du temps de la classe en PDF. */
    public function pdf(string $classeId): Response
    {
        $this->authorizeAdminManagerOrSupervisor();

        $data                = $this->service->dataForPdf($classeId);
        $data['etablissement'] = Etablissement::first();
        $data['logo']        = $data['etablissement']?->logoDataUri();
        $data                += ColorHelper::palette(Parametrage::couleurPrincipale());

        $pdf = Pdf::loadView('pdf.emploi-du-temps', $data)
            ->setPaper('a4', 'landscape');

        $classe   = $data['classe'];
        $filename = 'emploi-du-temps-' . Str::slug($classe->nom) . '.pdf';

        return $pdf->download($filename);
    }
}
