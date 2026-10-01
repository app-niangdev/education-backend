<?php

namespace App\Repositories;

use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use App\Interfaces\ConversationRepositoryInterface;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConversationRepository implements ConversationRepositoryInterface
{
    /**
     * Les colonnes de « users » sont listees explicitement : charger le modele
     * entier ferait transiter des champs sans objet ici (adresse, telephones),
     * dans une liste qui peut compter des centaines de lignes.
     */
    private const RELATIONS = [
        'tuteur:id,nom,prenom,telephone_principal,email',
        'eleve:id,nom,prenom,matricule',
        'agent:id,first_name,last_name',
        'dernierMessage',
        'dernierMessage.expediteur:id,first_name,last_name,role_id',
    ];

    public function paginatePourAgent(User $agent, int $perPage, array $filters): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->visiblesParRole($agent->role?->name ?? '');

        return $this->filtrer($query, $filters)
            ->with(self::RELATIONS)
            ->withCount($this->nonLusCountSubquery($agent))
            ->orderByDesc('derniere_activite_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginatePourTuteur(int $tuteurId, int $perPage, array $filters): LengthAwarePaginator
    {
        $query = Conversation::query()->where('tuteur_id', $tuteurId);

        return $this->filtrer($query, $filters)
            ->with(self::RELATIONS)
            ->orderByDesc('derniere_activite_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): Conversation
    {
        return Conversation::with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $data): Conversation
    {
        return Conversation::create($data);
    }

    public function update(Conversation $conversation, array $data): Conversation
    {
        $conversation->update($data);

        return $conversation->fresh(self::RELATIONS);
    }

    public function messages(Conversation $conversation, int $perPage): LengthAwarePaginator
    {
        return $conversation->messages()
            ->with('expediteur:id,first_name,last_name,role_id', 'expediteur.role:id,name,label')
            // Ordre chronologique : un fil se lit du haut vers le bas.
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * L'ecriture du message et la mise a jour de l'activite du fil vont
     * ensemble : un message enregistre sans remonter le fil en tete de liste
     * passerait inapercu du service.
     */
    public function ajouterMessage(Conversation $conversation, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $data) {
            $message = $conversation->messages()->create($data);

            $conversation->forceFill([
                'derniere_activite_at' => $message->created_at,
            ])->save();

            // L'auteur a lu ce qu'il vient d'ecrire : sans cela son propre
            // message lui reviendrait comme non-lu au prochain calcul.
            if (!empty($data['expediteur_id'])) {
                $this->enregistrerLecture(
                    $conversation->id,
                    (int) $data['expediteur_id'],
                    $message->id,
                );
            }

            return $message->load('expediteur:id,first_name,last_name,role_id');
        });
    }

    public function comptePourNonLus(Conversation $conversation, User $user): int
    {
        $dernierLu = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('dernier_message_lu_id') ?? 0;

        return $conversation->messages()
            ->where('id', '>', $dernierLu)
            ->where('est_systeme', false)
            // Ses propres messages ne sont jamais des non-lus.
            ->where(fn ($q) => $q->whereNull('expediteur_id')->orWhere('expediteur_id', '!=', $user->id))
            ->count();
    }

    /**
     * Le badge global. Une seule requete agregee : boucler sur les fils pour
     * les compter un par un ferait une requete par conversation, a chaque
     * rafraichissement de la barre d'outils.
     */
    public function totalNonLus(User $user): int
    {
        $portee = $this->porteeDe($user);

        if ($portee === null) {
            return 0;
        }

        return (int) Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->leftJoin('conversation_participants', function ($join) use ($user) {
                $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                    ->where('conversation_participants.user_id', '=', $user->id);
            })
            ->whereNull('conversations.deleted_at')
            ->whereNull('messages.deleted_at')
            ->where('messages.est_systeme', false)
            ->where(function ($q) use ($user) {
                $q->whereNull('messages.expediteur_id')
                    ->orWhere('messages.expediteur_id', '!=', $user->id);
            })
            ->whereRaw('messages.id > COALESCE(conversation_participants.dernier_message_lu_id, 0)')
            ->tap($portee)
            ->count('messages.id');
    }

    public function marquerLu(Conversation $conversation, User $user): void
    {
        $dernierId = (int) $conversation->messages()->max('id');

        $this->enregistrerLecture($conversation->id, $user->id, $dernierId);
    }

    public function compteursParStatut(User $agent): array
    {
        $lignes = Conversation::query()
            ->visiblesParRole($agent->role?->name ?? '')
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $compteurs = [];

        foreach (StatutConversationEnum::cases() as $statut) {
            $compteurs[$statut->value] = (int) ($lignes[$statut->value] ?? 0);
        }

        return $compteurs;
    }

    /**
     * Restreint une requete aux fils que la personne a le droit de voir : ses
     * propres fils si c'est un tuteur, ceux de ses services si c'est un agent.
     * Retourne null quand la personne n'a acces a rien.
     */
    private function porteeDe(User $user): ?\Closure
    {
        if ($user->estTuteur()) {
            $tuteurId = $user->tuteur?->id;

            if ($tuteurId === null) {
                return null;
            }

            return fn (Builder $q) => $q->where('conversations.tuteur_id', $tuteurId);
        }

        $role = $user->role?->name;

        if ($role === null) {
            return null;
        }

        $services = array_column(
            array_values(array_filter(
                ServiceDestinataireEnum::cases(),
                fn (ServiceDestinataireEnum $s) => in_array($role, $s->rolesTraitants(), true),
            )),
            'value',
        );

        if ($services === []) {
            return null;
        }

        return fn (Builder $q) => $q->whereIn('conversations.service', $services);
    }

    /**
     * La lecture n'avance jamais a rebours : GREATEST protege du cas ou une
     * requete tardive porterait un id inferieur a ce qui est deja enregistre,
     * ce qui ferait reapparaitre des messages deja lus.
     */
    private function enregistrerLecture(int $conversationId, int $userId, int $messageId): void
    {
        $participant = ConversationParticipant::firstOrNew([
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
        ]);

        $participant->dernier_message_lu_id = max(
            (int) ($participant->dernier_message_lu_id ?? 0),
            $messageId,
        );
        $participant->lu_at = now();
        $participant->save();
    }

    /** Sous-requete du compteur de non-lus, attachee a chaque ligne de liste. */
    private function nonLusCountSubquery(User $user): array
    {
        return [
            'messages as non_lus_count' => function ($query) use ($user) {
                $query->where('est_systeme', false)
                    ->where(function ($q) use ($user) {
                        $q->whereNull('expediteur_id')
                            ->orWhere('expediteur_id', '!=', $user->id);
                    })
                    ->whereRaw(
                        'messages.id > COALESCE((
                            SELECT cp.dernier_message_lu_id
                            FROM conversation_participants cp
                            WHERE cp.conversation_id = conversations.id
                              AND cp.user_id = ?
                        ), 0)',
                        [$user->id],
                    );
            },
        ];
    }

    private function filtrer(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['service'] ?? null, fn ($q, $v) => $q->where('service', $v))
            ->when($filters['statut']  ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filters['eleve_id'] ?? null, fn ($q, $v) => $q->where('eleve_id', $v))
            ->when($filters['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            // « A traiter » : tout ce qui n'est ni resolu ni archive.
            ->when($filters['en_cours'] ?? null, fn ($q) => $q->enCours())
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('sujet', 'ilike', "%{$v}%")
                        ->orWhereHas('tuteur', function ($t) use ($v) {
                            $t->where('nom', 'ilike', "%{$v}%")
                                ->orWhere('prenom', 'ilike', "%{$v}%")
                                // Nom complet dans les deux ordres.
                                ->orWhereRaw("(prenom || ' ' || nom) ilike ?", ["%{$v}%"])
                                ->orWhereRaw("(nom || ' ' || prenom) ilike ?", ["%{$v}%"]);
                        })
                        ->orWhereHas('messages', fn ($m) => $m->where('corps', 'ilike', "%{$v}%"));
                });
            });
    }
}
