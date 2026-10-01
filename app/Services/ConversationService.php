<?php

namespace App\Services;

use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use App\Events\ConversationMiseAJour;
use App\Events\MessageEnvoye;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\ConversationServiceInterface;
use App\Interfaces\JustificatifServiceInterface;
use App\Models\Conversation;
use App\Models\Eleve;
use App\Models\Message;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * La messagerie entre les tuteurs et les services de l'etablissement.
 *
 * Deux principes commandent tout ce qui suit :
 *
 * 1. Un fil est adresse a un service, jamais a une personne. N'importe quel
 *    agent habilite peut repondre ; l'absence d'un collegue ne laisse pas une
 *    famille sans reponse.
 *
 * 2. La direction ne se saisit pas, elle se remonte. Le tuteur choisit entre
 *    la scolarite et la tresorerie ; c'est l'agent qui escalade quand il ne
 *    peut pas trancher. Sans cette regle, la direction deviendrait le premier
 *    guichet et les services competents seraient court-circuites.
 */
class ConversationService implements ConversationServiceInterface
{
    public function __construct(
        private readonly ConversationRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface     $activityLog,
        private readonly JustificatifServiceInterface    $justificatifs,
    ) {}

    public function lister(User $user, int $perPage, array $filters): LengthAwarePaginator
    {
        if ($user->estTuteur()) {
            return $this->repository->paginatePourTuteur(
                $this->tuteurDe($user)->id,
                $perPage,
                $filters,
            );
        }

        $this->refuserSiPasAgent($user);

        return $this->repository->paginatePourAgent($user, $perPage, $filters);
    }

    public function consulter(int|string $id, User $user): Conversation
    {
        $conversation = $this->repository->findById($id);

        $this->autoriserAcces($conversation, $user);

        return $conversation;
    }

    public function messages(int|string $id, User $user, int $perPage): LengthAwarePaginator
    {
        $conversation = $this->consulter($id, $user);

        // Ouvrir un fil vaut lecture : le compteur retomberait sinon a chaque
        // consultation, obligeant le client a un second appel.
        $this->repository->marquerLu($conversation, $user);

        return $this->repository->messages($conversation, $perPage);
    }

    /**
     * Ouvre un fil. Le premier message part dans la foulee : un fil sans
     * message n'a pas de sens pour celui qui le recoit, il ne saurait pas de
     * quoi il retourne.
     */
    public function ouvrir(array $data, User $user): Conversation
    {
        $service = ServiceDestinataireEnum::from($data['service']);

        $tuteurId = $this->resoudreTuteurId($data, $user, $service);

        $this->verifierEleveDuTuteur($data['eleve_id'] ?? null, $tuteurId);

        return DB::transaction(function () use ($data, $user, $service, $tuteurId) {
            $conversation = $this->repository->create([
                'tuteur_id'            => $tuteurId,
                'eleve_id'             => $data['eleve_id'] ?? null,
                'service'              => $service->value,
                'sujet'                => $data['sujet'],
                'statut'               => StatutConversationEnum::OUVERTE->value,
                'derniere_activite_at' => now(),
            ]);

            $message = $this->repository->ajouterMessage($conversation, [
                'expediteur_id' => $user->id,
                'corps'         => $data['corps'],
                'est_systeme'   => false,
            ]);

            $conversation = $this->repository->findById($conversation->id);

            broadcast(new MessageEnvoye($message, $conversation))->toOthers();

            $this->activityLog->log(
                user:        $user,
                action:      'created',
                module:      'messagerie',
                description: "Ouverture de la conversation « {$conversation->sujet} » ({$service->libelle()})",
                subject:     $conversation,
                newValues:   $conversation->toArray(),
            );

            return $conversation;
        });
    }

    public function repondre(int|string $id, string $corps, User $user): Message
    {
        $conversation = $this->consulter($id, $user);

        if ($conversation->estClos()) {
            abort(422, 'Cette conversation est archivée : elle n\'accepte plus de message.');
        }

        return DB::transaction(function () use ($conversation, $corps, $user) {
            $message = $this->repository->ajouterMessage($conversation, [
                'expediteur_id' => $user->id,
                'corps'         => $corps,
                'est_systeme'   => false,
            ]);

            $this->avancerStatutApresReponse($conversation, $user);

            $conversation = $this->repository->findById($conversation->id);

            broadcast(new MessageEnvoye($message, $conversation))->toOthers();

            return $message;
        });
    }

    public function prendreEnCharge(int|string $id, User $user): Conversation
    {
        $conversation = $this->consulter($id, $user);

        $this->refuserSiPasAgent($user);

        if ($conversation->agent_id === $user->id) {
            return $conversation;
        }

        $conversation = $this->repository->update($conversation, [
            'agent_id' => $user->id,
            'statut'   => $conversation->statut === StatutConversationEnum::OUVERTE
                ? StatutConversationEnum::EN_COURS->value
                : $conversation->statut->value,
        ]);

        $this->messageSysteme(
            $conversation,
            "{$user->full_name} a pris en charge cette demande.",
        );

        broadcast(new ConversationMiseAJour($conversation));

        return $conversation;
    }

    /**
     * Remonte le fil a la direction. Reserve aux agents du service en cours :
     * le tuteur ne decide pas de solliciter la direction, et un agent d'un
     * autre guichet n'a pas a arbitrer un dossier qu'il ne traite pas.
     */
    public function escalader(int|string $id, string $motif, User $user): Conversation
    {
        $conversation = $this->consulter($id, $user);

        $this->refuserSiPasAgent($user);

        if ($conversation->service === ServiceDestinataireEnum::DIRECTION) {
            abort(422, 'Cette conversation est déjà traitée par la direction.');
        }

        if ($conversation->estClos()) {
            abort(422, 'Une conversation archivée ne peut plus être escaladée.');
        }

        $servicePrecedent = $conversation->service->value;

        $conversation = $this->repository->update($conversation, [
            'service'         => ServiceDestinataireEnum::DIRECTION->value,
            'service_origine' => $servicePrecedent,
            'statut'          => StatutConversationEnum::ESCALADEE->value,
            'escaladee_at'    => now(),
            'motif_escalade'  => $motif,
            // Le fil change de main : l'agent precedent n'en repond plus.
            'agent_id'        => null,
        ]);

        $this->messageSysteme(
            $conversation,
            "Demande escaladée à la direction par {$user->full_name}. Motif : {$motif}",
        );

        broadcast(new ConversationMiseAJour($conversation, $servicePrecedent));

        $this->activityLog->log(
            user:        $user,
            action:      'updated',
            module:      'messagerie',
            description: "Escalade à la direction de « {$conversation->sujet} » — motif : {$motif}",
            subject:     $conversation,
        );

        return $conversation;
    }

    public function changerStatut(int|string $id, string $statut, User $user): Conversation
    {
        $conversation = $this->consulter($id, $user);

        // Le statut decrit le traitement par l'etablissement : c'est aux
        // agents d'en repondre, pas au tuteur.
        $this->refuserSiPasAgent($user);

        $nouveau = StatutConversationEnum::from($statut);

        if ($nouveau === StatutConversationEnum::ESCALADEE) {
            abort(422, 'L\'escalade passe par l\'action dédiée, afin d\'en consigner le motif.');
        }

        $ancien = $conversation->statut;

        $conversation = $this->repository->update($conversation, ['statut' => $nouveau->value]);

        $this->messageSysteme(
            $conversation,
            "Statut passé de « {$ancien->libelle()} » à « {$nouveau->libelle()} » par {$user->full_name}.",
        );

        broadcast(new ConversationMiseAJour($conversation));

        return $conversation;
    }

    public function marquerLu(int|string $id, User $user): void
    {
        $this->repository->marquerLu($this->consulter($id, $user), $user);
    }

    /**
     * Regenere et renvoie le justificatif porte par un message.
     *
     * L'acces au FIL commande l'acces a la PIECE : consulter() refuse deja un
     * tuteur qui n'est pas proprietaire, ou un agent d'un autre guichet. On
     * verifie ensuite que le message appartient bien a ce fil — sans quoi il
     * suffirait de connaitre l'id d'un message pour le lire depuis n'importe
     * quelle conversation accessible.
     */
    public function telechargerPieceJointe(
        int|string $conversationId,
        int|string $messageId,
        User $user,
    ): Response {
        $conversation = $this->consulter($conversationId, $user);

        $message = Message::where('conversation_id', $conversation->id)
            ->where('id', $messageId)
            ->first();

        if ($message === null) {
            abort(404, 'Message introuvable dans cette conversation.');
        }

        if ($message->piece_jointe_type === null || $message->piece_jointe_id === null) {
            abort(404, 'Ce message ne porte aucun justificatif.');
        }

        return $this->justificatifs->rendre(
            $message->piece_jointe_type,
            $message->piece_jointe_id,
        );
    }

    public function totalNonLus(User $user): int
    {
        return $this->repository->totalNonLus($user);
    }

    /**
     * Ce que le demandeur peut choisir a l'ouverture d'un fil. Le tuteur ne
     * voit que les guichets ouverts a la saisie, et seulement ses enfants.
     */
    public function referentiels(User $user): array
    {
        $services = $user->estTuteur()
            ? ServiceDestinataireEnum::ouvertsAuTuteur()
            : ServiceDestinataireEnum::cases();

        $referentiels = [
            'services' => array_map(
                fn (ServiceDestinataireEnum $s) => ['valeur' => $s->value, 'libelle' => $s->libelle()],
                array_values($services),
            ),
            'statuts' => array_map(
                fn (StatutConversationEnum $s) => ['valeur' => $s->value, 'libelle' => $s->libelle()],
                StatutConversationEnum::cases(),
            ),
        ];

        if ($user->estTuteur()) {
            $referentiels['eleves'] = $this->tuteurDe($user)
                ->eleves()
                ->select('id', 'nom', 'prenom', 'matricule')
                ->orderBy('prenom')
                ->get();
        }

        if (!$user->estTuteur()) {
            $referentiels['compteurs'] = $this->repository->compteursParStatut($user);
        }

        return $referentiels;
    }

    /**
     * Le tuteur ecrit toujours en son nom : l'id vient de son compte, jamais
     * du corps de la requete, sans quoi il pourrait ouvrir un fil au nom d'une
     * autre famille. L'agent, lui, doit designer le tuteur concerne.
     */
    private function resoudreTuteurId(array $data, User $user, ServiceDestinataireEnum $service): int
    {
        if ($user->estTuteur()) {
            if (!$service->ouvertAuTuteur()) {
                abort(422, 'La direction ne se saisit pas directement : adressez votre demande à la scolarité ou à la trésorerie, qui la transmettra si nécessaire.');
            }

            return $this->tuteurDe($user)->id;
        }

        $this->refuserSiPasAgent($user);

        if (empty($data['tuteur_id'])) {
            abort(422, 'Le tuteur destinataire est obligatoire.');
        }

        return (int) $data['tuteur_id'];
    }

    /** Un fil ne peut porter que sur un eleve du tuteur concerne. */
    private function verifierEleveDuTuteur(int|string|null $eleveId, int $tuteurId): void
    {
        if ($eleveId === null) {
            return;
        }

        $appartient = Eleve::where('id', $eleveId)
            ->where('tuteur_id', $tuteurId)
            ->exists();

        if (!$appartient) {
            abort(422, "L'élève sélectionné n'est pas rattaché à ce tuteur.");
        }
    }

    /**
     * Qui a le droit d'ouvrir ce fil : le tuteur proprietaire, ou un agent
     * dont le role traite le service destinataire.
     */
    private function autoriserAcces(Conversation $conversation, User $user): void
    {
        if ($user->estTuteur()) {
            if ($conversation->tuteur_id !== $this->tuteurDe($user)->id) {
                abort(403, 'Cette conversation ne vous concerne pas.');
            }

            return;
        }

        $role = $user->role?->name;

        if ($role === null || !in_array($role, $conversation->service->rolesTraitants(), true)) {
            abort(403, 'Votre service ne traite pas cette conversation.');
        }
    }

    /**
     * Le compte tuteur doit etre rattache a une fiche : un compte cree sans
     * lien ne saurait pas de quelle famille il parle.
     */
    private function tuteurDe(User $user): Tuteur
    {
        $tuteur = $user->tuteur;

        if ($tuteur === null) {
            abort(403, 'Votre compte n\'est rattaché à aucune fiche tuteur. Contactez l\'établissement.');
        }

        return $tuteur;
    }

    private function refuserSiPasAgent(User $user): void
    {
        $roles = ['admin', 'manager', 'supervisor', 'treasurer'];

        if (!in_array($user->role?->name, $roles, true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Une reponse d'agent fait passer le fil « en cours » : le statut suit le
     * traitement reel sans que personne ait a le declarer. On ne touche pas
     * aux fils escalades ou archives, dont l'etat porte une decision.
     */
    private function avancerStatutApresReponse(Conversation $conversation, User $user): void
    {
        if ($user->estTuteur()) {
            // Le tuteur relance un fil clos : il redevient a traiter.
            if ($conversation->statut === StatutConversationEnum::RESOLUE) {
                $this->repository->update($conversation, [
                    'statut' => StatutConversationEnum::OUVERTE->value,
                ]);
            }

            return;
        }

        if ($conversation->statut === StatutConversationEnum::OUVERTE) {
            $this->repository->update($conversation, [
                'statut'   => StatutConversationEnum::EN_COURS->value,
                'agent_id' => $conversation->agent_id ?? $user->id,
            ]);
        }
    }

    /** Trace un changement d'etat dans le fil lui-meme, pour que les deux parties le voient. */
    private function messageSysteme(Conversation $conversation, string $corps): void
    {
        $message = $this->repository->ajouterMessage($conversation, [
            'expediteur_id' => null,
            'corps'         => $corps,
            'est_systeme'   => true,
        ]);

        broadcast(new MessageEnvoye($message, $conversation));
    }
}
