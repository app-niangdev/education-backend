<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Helpers\ApiResponse;
use App\Interfaces\NotesTuteurServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les resultats scolaires consultes par la famille.
 *
 * Reserve au role tuteur. L'administrateur n'y entre pas : ces routes ne
 * repondent que sur les enfants du compte connecte, et un agent sans fiche
 * tuteur n'y verrait rien. Le personnel a ses propres ecrans — bulletins,
 * grilles de notes — qui montrent les classes entieres.
 */
class NotesTuteurController extends Controller
{
    public function __construct(
        private readonly NotesTuteurServiceInterface $service,
    ) {}

    /** Les enfants rattaches au compte, pour le selecteur de l'ecran. */
    public function mesEleves(Request $request): JsonResponse
    {
        $this->assertTuteur($request);

        return ApiResponse::success(
            $this->service->mesEleves($request->user()),
            'Élèves récupérés avec succès'
        );
    }

    /**
     * Le releve d'un enfant pour une periode.
     *
     * L'identifiant vient du client, mais le service le confronte a la liste
     * des enfants du tuteur : changer le numero dans l'URL ne donne pas acces
     * aux notes d'un autre eleve.
     */
    public function releve(Request $request, string $eleveId): JsonResponse
    {
        $this->assertTuteur($request);

        return ApiResponse::success(
            $this->service->relevePourEleve(
                $request->user(),
                $eleveId,
                $request->input('periode_id')
            ),
            'Relevé de notes récupéré avec succès'
        );
    }

    private function assertTuteur(Request $request): void
    {
        if ((int) $request->user()->role_id !== RoleEnum::Tuteur->value) {
            abort(403, "Cet espace est réservé aux tuteurs.");
        }
    }
}
