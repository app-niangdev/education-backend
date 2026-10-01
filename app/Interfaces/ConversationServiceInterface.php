<?php

namespace App\Interfaces;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;

interface ConversationServiceInterface
{
    /** La liste adaptee au demandeur : sa corbeille s'il est agent, ses fils s'il est tuteur. */
    public function lister(User $user, int $perPage, array $filters): LengthAwarePaginator;

    /** Un fil, apres verification que le demandeur a le droit de le voir. */
    public function consulter(int|string $id, User $user): Conversation;

    /** Les messages d'un fil ; marque le fil comme lu au passage. */
    public function messages(int|string $id, User $user, int $perPage): LengthAwarePaginator;

    /** Ouvre un fil. Un tuteur ne peut viser qu'un guichet ouvert a la saisie. */
    public function ouvrir(array $data, User $user): Conversation;

    public function repondre(int|string $id, string $corps, User $user): Message;

    /** Un agent se declare en charge du fil. */
    public function prendreEnCharge(int|string $id, User $user): Conversation;

    /** Remonte le fil a la direction, faute de pouvoir trancher. */
    public function escalader(int|string $id, string $motif, User $user): Conversation;

    public function changerStatut(int|string $id, string $statut, User $user): Conversation;

    public function marquerLu(int|string $id, User $user): void;

    /**
     * Regenere le justificatif porte par un message, apres avoir verifie que
     * le demandeur a bien acces au fil qui le porte.
     */
    public function telechargerPieceJointe(
        int|string $conversationId,
        int|string $messageId,
        User $user,
    ): Response;

    /** Le badge de la barre d'outils : nombre de messages non lus. */
    public function totalNonLus(User $user): int;

    /** Ce que le demandeur peut choisir a l'ouverture d'un fil. */
    public function referentiels(User $user): array;
}
