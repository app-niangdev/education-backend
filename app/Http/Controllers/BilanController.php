<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Interfaces\BilanServiceInterface;
use App\Models\AnneeScolaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le bilan financier de l'etablissement.
 *
 * Reserve au tresorier, au manager et a l'administrateur : il agrege des
 * montants (resultat, masse salariale, creances) qui n'ont a etre lus ni par
 * un enseignant ni par un surveillant.
 */
class BilanController extends Controller
{
    public function __construct(
        private readonly BilanServiceInterface $service
    ) {}

    /**
     * Bilan de l'annee scolaire ciblee (annee_scolaire_id), ou de l'annee en
     * cours par defaut, eventuellement restreint a un mois (mois + annee).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeBilan($request);

        $request->validate([
            'annee_scolaire_id' => ['nullable', 'integer', 'exists:annee_scolaires,id'],
            'mois'              => ['nullable', 'integer', 'between:1,12'],
            // Le mois seul est ambigu : une annee scolaire couvre deux
            // millesimes. L'annee civile l'accompagne donc toujours.
            'annee'             => ['nullable', 'integer', 'digits:4', 'required_with:mois'],
        ]);

        $annee = $request->filled('annee_scolaire_id')
            ? AnneeScolaire::findOrFail($request->integer('annee_scolaire_id'))
            : null;

        return ApiResponse::success(
            $this->service->bilan(
                $annee,
                $request->filled('mois')  ? $request->integer('mois')  : null,
                $request->filled('annee') ? $request->integer('annee') : null,
            ),
            'Bilan financier récupéré avec succès'
        );
    }

    /**
     * Le bilan expose la sante financiere de l'etablissement : il suit les
     * memes droits que le tableau de bord tresorier.
     */
    private function authorizeBilan(Request $request): void
    {
        if (!in_array($request->user()?->role?->name, ['treasurer', 'manager', 'admin'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
