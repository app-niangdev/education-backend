<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un message vient d'etre ecrit dans un fil.
 *
 * Diffuse sur deux canaux : celui du fil, pour les fenetres deja ouvertes
 * dessus, et celui du service, pour que les agents voient arriver une demande
 * sans avoir ouvert quoi que ce soit. Sans le second, un tuteur qui ecrit a la
 * tresorerie n'apparaitrait qu'au prochain rechargement de page.
 *
 * ShouldBroadcastNow et non ShouldBroadcast : ce dernier passe la diffusion
 * par la file d'attente, et l'application tourne sur QUEUE_CONNECTION=database.
 * Sans worker lance en permanence, les evenements s'empilaient dans la table
 * « jobs » et n'atteignaient jamais Reverb — il fallait recharger la page pour
 * voir un message. Une messagerie ne peut pas dependre d'un worker : le
 * message part maintenant, dans la meme requete que son enregistrement.
 *
 * Le cout est assume : l'envoi vers Reverb est un appel HTTP local, borne a
 * 5 secondes (voir config/broadcasting.php).
 */
class MessageEnvoye implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversation->id}"),
            new PrivateChannel("service.{$this->conversation->service->value}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.envoye';
    }

    /**
     * La charge utile est construite a la main plutot que serialisee depuis le
     * modele : elle part vers des navigateurs, et le modele complet exposerait
     * des champs sans usage cote client.
     */
    public function broadcastWith(): array
    {
        $expediteur = $this->message->expediteur;

        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'corps'           => $this->message->corps,
            'est_systeme'     => $this->message->est_systeme,
            'created_at'      => $this->message->created_at?->toIso8601String(),

            // Le justificatif voyage avec l'evenement : sans cela, un recu
            // arrive en direct s'afficherait sans son bouton de
            // telechargement jusqu'au prochain rechargement de la page.
            'piece_jointe_type'    => $this->message->piece_jointe_type?->value,
            'piece_jointe_id'      => $this->message->piece_jointe_id,
            'piece_jointe_libelle' => $this->message->piece_jointe_libelle,
            'a_piece_jointe'       => $this->message->a_piece_jointe,
            'expediteur'      => $expediteur === null ? null : [
                'id'        => $expediteur->id,
                'full_name' => $expediteur->full_name,
                'role'      => $expediteur->role?->name,
            ],
            // Permet a la liste des fils de se reordonner sans recharger.
            'conversation'    => [
                'id'                   => $this->conversation->id,
                'sujet'                => $this->conversation->sujet,
                'service'              => $this->conversation->service?->value,
                'statut'               => $this->conversation->statut?->value,
                'derniere_activite_at' => $this->conversation->derniere_activite_at?->toIso8601String(),
            ],
        ];
    }
}
