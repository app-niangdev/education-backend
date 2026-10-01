<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\EscaladerConversationRequest;
use App\Http\Requests\FiltreConversationRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Interfaces\ConversationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Enums\StatutConversationEnum;

/**
 * La messagerie entre les tuteurs et les services de l'etablissement.
 *
 * Le controleur ne decide de rien : la portee de chacun (quels fils, quelles
 * actions) est etablie dans ConversationService, qui est le seul a connaitre
 * la regle « un fil appartient a un service ». On evite ainsi que l'API et un
 * futur appelant (tache planifiee, commande artisan) divergent sur les droits.
 */
class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationServiceInterface $service
    ) {}

    /** Sa corbeille de service pour un agent, ses propres fils pour un tuteur. */
    public function index(FiltreConversationRequest $request): JsonResponse
    {
        $conversations = $this->service->lister(
            user:    $request->user(),
            perPage: (int) $request->input('per_page', 15),
            filters: $request->filtres(),
        );

        return ApiResponse::paginated($conversations, 'Conversations récupérées avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->consulter($id, $request->user()),
            'Conversation récupérée avec succès',
        );
    }

    /** Les messages d'un fil. L'ouverture vaut lecture : le compteur retombe. */
    public function messages(Request $request, string $id): JsonResponse
    {
        $messages = $this->service->messages(
            id:      $id,
            user:    $request->user(),
            perPage: (int) $request->input('per_page', 50),
        );

        return ApiResponse::paginated($messages, 'Messages récupérés avec succès');
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = $this->service->ouvrir($request->validated(), $request->user());

        return ApiResponse::success($conversation, 'Conversation ouverte avec succès', 201);
    }

    public function repondre(StoreMessageRequest $request, string $id): JsonResponse
    {
        $message = $this->service->repondre(
            id:    $id,
            corps: $request->validated('corps'),
            user:  $request->user(),
        );

        return ApiResponse::success($message, 'Message envoyé avec succès', 201);
    }

    /** Un agent se declare en charge : indicatif, il ne verrouille pas le fil. */
    public function prendreEnCharge(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->prendreEnCharge($id, $request->user()),
            'Conversation prise en charge',
        );
    }

    /**
     * Remonte le fil a la direction. C'est le seul chemin vers ce service :
     * un tuteur ne peut pas l'adresser directement.
     */
    public function escalader(EscaladerConversationRequest $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->service->escalader($id, $request->validated('motif'), $request->user()),
            'Conversation escaladée à la direction',
        );
    }

    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::enum(StatutConversationEnum::class)],
        ], [
            'statut.required' => 'Le statut est obligatoire.',
            'statut.enum'     => 'Le statut sélectionné est invalide.',
        ]);

        return ApiResponse::success(
            $this->service->changerStatut($id, $valide['statut'], $request->user()),
            'Statut de la conversation mis à jour',
        );
    }

    public function marquerLu(Request $request, string $id): JsonResponse
    {
        $this->service->marquerLu($id, $request->user());

        return ApiResponse::success(null, 'Conversation marquée comme lue');
    }

    /** Le badge de la barre d'outils. */
    public function nonLus(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['total' => $this->service->totalNonLus($request->user())],
            'Compteur de messages non lus récupéré',
        );
    }

    /**
     * Telecharge le justificatif porte par un message.
     *
     * Le PDF n'est pas stocke : il est regenere ici depuis le paiement. C'est
     * cette route, et non l'emplacement d'un fichier, qui protege le document —
     * elle refuse quiconque n'a pas acces au fil qui porte la piece. Un tuteur
     * qui devinerait l'identifiant d'un recu voisin repart avec un 403.
     */
    public function pieceJointe(Request $request, string $id, string $messageId): Response
    {
        return $this->service->telechargerPieceJointe($id, $messageId, $request->user());
    }

    /** Ce que le demandeur peut choisir : services accessibles, statuts, ses enfants. */
    public function referentiels(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->referentiels($request->user()),
            'Référentiels de la messagerie récupérés avec succès',
        );
    }
}
