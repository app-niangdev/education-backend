<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Le fil a change d'etat sans qu'un message soit ecrit : prise en charge,
 * escalade, resolution, archivage.
 *
 * Diffuse aussi vers le service d'origine lors d'une escalade : le surveillant
 * qui a remonte le dossier doit voir le fil quitter sa corbeille en direct,
 * sinon il continue de le croire a sa charge.
 *
 * ShouldBroadcastNow : meme raison que MessageEnvoye — sans worker de file
 * d'attente, une diffusion mise en queue n'atteint jamais Reverb.
 */
class ConversationMiseAJour implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?string $servicePrecedent = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $canaux = [
            new PrivateChannel("conversation.{$this->conversation->id}"),
            new PrivateChannel("service.{$this->conversation->service->value}"),
        ];

        if ($this->servicePrecedent !== null
            && $this->servicePrecedent !== $this->conversation->service->value) {
            $canaux[] = new PrivateChannel("service.{$this->servicePrecedent}");
        }

        return $canaux;
    }

    public function broadcastAs(): string
    {
        return 'conversation.maj';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                   => $this->conversation->id,
            'sujet'                => $this->conversation->sujet,
            'service'              => $this->conversation->service?->value,
            'service_origine'      => $this->conversation->service_origine?->value,
            'statut'               => $this->conversation->statut?->value,
            'agent'                => $this->conversation->agent === null ? null : [
                'id'        => $this->conversation->agent->id,
                'full_name' => $this->conversation->agent->full_name,
            ],
            'derniere_activite_at' => $this->conversation->derniere_activite_at?->toIso8601String(),
        ];
    }
}
