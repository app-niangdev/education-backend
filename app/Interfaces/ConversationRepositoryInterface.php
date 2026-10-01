<?php

namespace App\Interfaces;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    /** Les fils visibles par un agent, filtres et pagines. */
    public function paginatePourAgent(User $agent, int $perPage, array $filters): LengthAwarePaginator;

    /** Les fils d'un tuteur, du plus recemment actif au plus ancien. */
    public function paginatePourTuteur(int $tuteurId, int $perPage, array $filters): LengthAwarePaginator;

    public function findById(int|string $id): Conversation;

    public function create(array $data): Conversation;

    public function update(Conversation $conversation, array $data): Conversation;

    /** Les messages d'un fil, du plus ancien au plus recent. */
    public function messages(Conversation $conversation, int $perPage): LengthAwarePaginator;

    public function ajouterMessage(Conversation $conversation, array $data): Message;

    /**
     * Nombre de messages qu'une personne n'a pas encore lus dans un fil.
     * Les messages de la personne elle-meme et ceux du systeme n'y entrent pas.
     */
    public function comptePourNonLus(Conversation $conversation, User $user): int;

    /** Total des messages non lus, tous fils accessibles a cette personne. */
    public function totalNonLus(User $user): int;

    /** Enregistre jusqu'ou la personne a lu ce fil. */
    public function marquerLu(Conversation $conversation, User $user): void;

    /** Repartition des fils par statut, pour les compteurs d'en-tete. */
    public function compteursParStatut(User $agent): array;
}
