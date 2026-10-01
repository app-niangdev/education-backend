<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesByRole;
use App\Interfaces\CompteTuteurServiceInterface;
use App\Models\Tuteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les acces de connexion des tuteurs.
 *
 * Ouvrir un compte revient a donner a une famille une vue sur l'etablissement :
 * l'action est reservee a l'admin et au manager, comme les autres decisions
 * qui engagent l'ecole vis-a-vis de l'exterieur.
 *
 * Le mot de passe genere ne transite qu'une fois, dans la reponse a la
 * creation. Il n'existe aucune route pour le relire : la seule issue en cas de
 * perte est la reinitialisation, qui en produit un nouveau.
 */
class CompteTuteurController extends Controller
{
    use AuthorizesByRole;

    public function __construct(
        private readonly CompteTuteurServiceInterface $service,
    ) {}

    /**
     * La liste des tuteurs, avec l'etat de leur acces. Ouverte au surveillant
     * en consultation : il oriente les familles, il doit pouvoir dire si un
     * compte existe.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminManagerOrSupervisor($request);

        $tuteurs = Tuteur::query()
            ->with('user:id,email,username,status,must_change_password')
            ->withCount('eleves')
            ->when($request->input('search'), function ($q, $recherche) {
                $q->where(function ($sub) use ($recherche) {
                    $sub->where('nom', 'ilike', "%{$recherche}%")
                        ->orWhere('prenom', 'ilike', "%{$recherche}%")
                        ->orWhere('telephone_principal', 'ilike', "%{$recherche}%")
                        ->orWhere('email', 'ilike', "%{$recherche}%");
                });
            })
            // Filtre « sans compte » : c'est la liste de travail de l'agent
            // qui ouvre les acces en serie.
            ->when($request->boolean('sans_compte'), fn ($q) => $q->whereNull('user_id'))
            ->when($request->boolean('avec_compte'), fn ($q) => $q->whereNotNull('user_id'))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        return ApiResponse::paginated($tuteurs, 'Tuteurs récupérés avec succès');
    }

    public function store(Request $request, string $tuteurId): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $valide = $request->validate([
            // Facultatifs tous les deux : on retombe sur la fiche tuteur.
            // Le service exige au final un telephone — c'est l'identifiant de
            // connexion — mais accepte qu'il vienne de la fiche.
            'telephone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'     => ['sometimes', 'nullable', 'email', 'max:255'],
        ], [
            'email.email' => "L'adresse e-mail n'est pas valide.",
        ]);

        $resultat = $this->service->creer($tuteurId, $valide, $request->user());

        return ApiResponse::success([
            'tuteur'       => $resultat['tuteur'],
            // Affiche une seule fois cote client, a charge pour l'agent de le
            // transmettre a la famille.
            'mot_de_passe' => $resultat['mot_de_passe'],
        ], 'Accès créé avec succès', 201);
    }

    public function reinitialiser(Request $request, string $tuteurId): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        $resultat = $this->service->reinitialiserMotDePasse($tuteurId, $request->user());

        return ApiResponse::success([
            'tuteur'       => $resultat['tuteur'],
            'mot_de_passe' => $resultat['mot_de_passe'],
        ], 'Mot de passe réinitialisé avec succès');
    }

    public function revoquer(Request $request, string $tuteurId): JsonResponse
    {
        $this->authorizeAdminOrManager($request);

        return ApiResponse::success(
            $this->service->revoquer($tuteurId, $request->user()),
            'Accès révoqué avec succès',
        );
    }
}
