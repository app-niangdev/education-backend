<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Interfaces\TuteurRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'annuaire des tuteurs, consulte pendant la saisie d'un eleve.
 *
 * Distinct de CompteTuteurController, qui gere les acces de connexion : ici on
 * ne fait que retrouver une fiche existante pour la rattacher a un nouvel
 * eleve. Une fratrie partage un tuteur — sans cette recherche, l'agent le
 * resaisit a chaque enfant et l'etablissement se retrouve avec autant de
 * fiches que de freres et soeurs.
 *
 * Ouvert au surveillant : c'est lui qui saisit les eleves au quotidien.
 */
class AnnuaireTuteurController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly TuteurRepositoryInterface $repository,
    ) {}

    public function rechercher(Request $request): JsonResponse
    {
        $this->authorizeParcoursInscription($request);

        $tuteurs = $this->repository->rechercher(
            recherche: (string) $request->input('q', ''),
            // Borne des deux cotes : le plafond protege la base, le plancher
            // evite qu'un « limite=0 » ne rende une liste vide sans raison.
            limite:    max(1, min((int) $request->input('limite', 10), 25)),
        );

        return ApiResponse::success($tuteurs, 'Tuteurs trouvés');
    }
}
