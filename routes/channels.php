<?php

use App\Enums\ServiceDestinataireEnum;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canaux de diffusion
|--------------------------------------------------------------------------
|
| Ces regles decident qui peut ECOUTER quoi. Elles doublent volontairement les
| controles de ConversationService : celui-ci protege les reponses HTTP, alors
| qu'un canal WebSocket pousse les messages sans qu'aucune route soit appelee.
| Sans ces callbacks, n'importe quel compte authentifie pourrait s'abonner au
| canal d'une conversation qui ne le concerne pas et lire les echanges d'une
| autre famille en direct.
|
| L'authentification passe par le middleware jwt.auth (voir routes/api.php) :
| l'application n'a pas de session, le garde « web » par defaut ne verrait
| jamais l'utilisateur.
|
*/

/**
 * Le fil lui-meme : le tuteur proprietaire, et les agents dont le role traite
 * le service destinataire.
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    if ($conversation === null) {
        return false;
    }

    if ($user->estTuteur()) {
        return $conversation->tuteur_id === $user->tuteur?->id;
    }

    return in_array($user->role?->name, $conversation->service->rolesTraitants(), true);
});

/**
 * La corbeille d'un service : les agents qui le traitent, et eux seuls.
 *
 * C'est ce canal qui fait apparaitre une demande chez un agent qui n'a rien
 * ouvert. Un tuteur n'y a jamais acces : il y verrait les demandes de toutes
 * les autres familles.
 */
Broadcast::channel('service.{service}', function (User $user, string $service) {
    if ($user->estTuteur()) {
        return false;
    }

    $destinataire = ServiceDestinataireEnum::tryFrom($service);

    if ($destinataire === null) {
        return false;
    }

    return in_array($user->role?->name, $destinataire->rolesTraitants(), true);
});
