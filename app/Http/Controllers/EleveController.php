<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Http\Requests\ImportElevesRequest;
use App\Http\Requests\StoreEleveRequest;
use App\Http\Requests\UpdateEleveRequest;
use App\Interfaces\EleveServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EleveController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly EleveServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeParcoursInscription();

        $eleves = $this->service->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
            filters: $request->only(['matricule', 'classe_actuelle_id', 'statut_inscription', 'sexe']),
        );

        return ApiResponse::paginated($eleves, 'Liste des élèves récupérée avec succès');
    }

    public function all(): JsonResponse
    {
        return ApiResponse::success($this->service->all(), 'Élèves récupérés avec succès');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeParcoursInscription();

        return ApiResponse::success($this->service->find($id));
    }

    public function store(StoreEleveRequest $request): JsonResponse
    {
        $eleve = $this->service->create($request->validated(), $request->user());

        return ApiResponse::success($eleve, 'Élève créé avec succès', 201);
    }

    /**
     * Reprise de donnees : import en masse depuis un tableur.
     *
     * Le fichier est lu par le navigateur, qui envoie ici du JSON deja mis en
     * forme. La reponse reste un 200 meme lorsque des lignes ont ete ignorees
     * ou ont echoue : l'import est partiel par construction (voir
     * EleveService::importer), et le rapport dit ce qui est passe. Un code
     * d'erreur laisserait croire que rien n'a ete enregistre.
     */
    public function import(ImportElevesRequest $request): JsonResponse
    {
        $rapport = $this->service->importer(
            $request->validated()['lignes'],
            $request->user(),
        );

        return ApiResponse::success(
            $rapport,
            sprintf(
                '%d élève(s) importé(s), %d ignoré(s), %d en échec',
                $rapport['crees'],
                $rapport['ignores'],
                $rapport['echecs'],
            ),
        );
    }

    public function update(UpdateEleveRequest $request, string $id): JsonResponse
    {
        $eleve = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success($eleve, 'Élève modifié avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $this->service->delete($id, $request->user());

        return ApiResponse::success(null, 'Élève supprimé avec succès');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdminOrManager();

        $eleve = $this->service->restore($id, $request->user());

        return ApiResponse::success($eleve, 'Élève restauré avec succès');
    }
}
