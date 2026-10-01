<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ColorHelper;
use App\Http\Requests\EnregistrerAppelRequest;
use App\Http\Requests\JustifierPresenceRequest;
use App\Http\Requests\UpdatePresenceRequest;
use App\Interfaces\AssiduiteServiceInterface;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assiduité : appel par créneau, justification, suivi et impression.
 *
 * Aucune garde de rôle ici : l'assiduité ne se découpe pas en niveaux
 * d'accès simples. Un enseignant consulte, mais seulement ses classes ; il
 * fait l'appel, mais seulement sur ses créneaux et dans la semaine. Ces
 * nuances vivent dans le service, comme pour les évaluations et les bulletins.
 */
class AssiduiteController extends Controller
{
    public function __construct(
        private readonly AssiduiteServiceInterface $service
    ) {}

    /** Statuts de présence et de séance, et le délai de saisie enseignant. */
    public function meta(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->meta(),
            "Métadonnées de l'assiduité récupérées avec succès"
        );
    }

    /** Les créneaux d'une journée : ceux de l'enseignant, ou toute l'école. */
    public function mesCreneaux(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'      => ['sometimes', 'date'],
            'classe_id' => ['sometimes', 'integer', 'exists:classes,id'],
        ], [
            'classe_id.exists' => 'La classe sélectionnée est invalide.',
        ]);

        return ApiResponse::success(
            $this->service->creneauxDuJour(
                $request->user(),
                $data['date'] ?? now()->toDateString(),
                isset($data['classe_id']) ? (int) $data['classe_id'] : null,
            ),
            'Créneaux récupérés avec succès'
        );
    }

    /** La feuille d'appel d'un créneau : les élèves et les anomalies saisies. */
    public function feuilleAppel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emploi_du_temps_id' => ['required', 'integer', 'exists:emploi_du_temps,id'],
            'date'               => ['required', 'date'],
        ], [
            'emploi_du_temps_id.required' => 'Le créneau est obligatoire.',
            'emploi_du_temps_id.exists'   => 'Le créneau sélectionné est invalide.',
            'date.required'               => "La date de l'appel est obligatoire.",
        ]);

        return ApiResponse::success(
            $this->service->feuilleAppel($request->user(), $data['emploi_du_temps_id'], $data['date']),
            "Feuille d'appel récupérée avec succès"
        );
    }

    public function enregistrerAppel(EnregistrerAppelRequest $request): JsonResponse
    {
        $seance = $this->service->enregistrerAppel($request->validated(), $request->user());

        return ApiResponse::success($seance, 'Appel enregistré avec succès', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $presences = $this->service->list(
            authUser:  $request->user(),
            perPage:   (int) $request->input('per_page', 10),
            search:    trim($request->input('search', '')),
            classeId:  $request->filled('classe_id') ? (int) $request->input('classe_id') : null,
            eleveId:   $request->filled('eleve_id') ? (int) $request->input('eleve_id') : null,
            statut:    $request->filled('statut') ? $request->input('statut') : null,
            justifie:  $request->filled('justifie') ? $request->boolean('justifie') : null,
            du:        $request->filled('du') ? $request->input('du') : null,
            au:        $request->filled('au') ? $request->input('au') : null,
        );

        return ApiResponse::paginated($presences, "Registre d'assiduité récupéré avec succès");
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->find($request->user(), $id),
            'Anomalie récupérée avec succès'
        );
    }

    /**
     * Justification, avec pièce jointe éventuelle.
     * En POST et non en PUT : la requête est un multipart, et PHP ne parse pas
     * le corps d'un PUT multipart.
     */
    public function justifier(JustifierPresenceRequest $request, string $id): JsonResponse
    {
        $presence = $this->service->justifier(
            $id,
            $request->validated(),
            $request->file('justificatif'),
            $request->user(),
        );

        return ApiResponse::success($presence, 'Justification enregistrée avec succès');
    }

    public function corriger(UpdatePresenceRequest $request, string $id): JsonResponse
    {
        $presence = $this->service->corriger($id, $request->validated(), $request->user());

        return ApiResponse::success($presence, 'Anomalie corrigée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->supprimer($id, $request->user());

        return ApiResponse::success(null, 'Anomalie supprimée avec succès');
    }

    public function ficheEleve(Request $request, string $eleveId): JsonResponse
    {
        $periodeId = $request->filled('periode_id') ? (int) $request->input('periode_id') : null;

        return ApiResponse::success(
            $this->service->ficheEleve($request->user(), $eleveId, $periodeId),
            "Fiche d'assiduité récupérée avec succès"
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $periodeId = $request->filled('periode_id') ? (int) $request->input('periode_id') : null;

        return ApiResponse::success(
            $this->service->dashboardSurveillant($request->user(), $request->input('date'), $periodeId),
            "Tableau de bord de l'assiduité récupéré avec succès"
        );
    }

    /** La fiche d'absences d'un élève, à remettre aux parents. */
    public function pdfFicheEleve(Request $request, string $eleveId): Response
    {
        $periodeId = $request->filled('periode_id') ? (int) $request->input('periode_id') : null;

        $data                  = $this->service->dataForPdfFicheEleve($request->user(), $eleveId, $periodeId);
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $pdf = Pdf::loadView('pdf.fiche-absences-eleve', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'absences-' . Str::slug($data['eleve']->nom_complet) . '.pdf';

        return $pdf->download($filename);
    }

    /** Une feuille d'appel vierge, pour faire l'appel sur papier. */
    public function pdfFeuilleVierge(Request $request): Response
    {
        $data = $request->validate([
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'date'      => ['required', 'date'],
        ], [
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists'   => 'La classe sélectionnée est invalide.',
            'date.required'      => 'La date est obligatoire.',
        ]);

        $donnees                  = $this->service->dataForPdfFeuilleVierge(
            $request->user(),
            (int) $data['classe_id'],
            $data['date'],
        );
        $donnees['etablissement'] = Etablissement::first();
        $donnees['logo']          = $donnees['etablissement']?->logoDataUri();
        $donnees                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        // Paysage : une colonne de pointage par créneau de la journée.
        $pdf = Pdf::loadView('pdf.feuille-appel-vierge', $donnees)
            ->setPaper('a4', 'landscape');

        $filename = 'feuille-appel-'
            . Str::slug($donnees['classe']->nom) . '-'
            . $donnees['date']->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
