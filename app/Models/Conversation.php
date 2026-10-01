<?php

namespace App\Models;

use App\Enums\ServiceDestinataireEnum;
use App\Enums\StatutConversationEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un fil de discussion entre un tuteur et un service de l'etablissement.
 *
 * Le fil appartient a un service, pas a un agent : voir la migration
 * create_conversations_table pour le raisonnement.
 */
class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tuteur_id',
        'eleve_id',
        'service',
        'sujet',
        'statut',
        'agent_id',
        'service_origine',
        'escaladee_at',
        'motif_escalade',
        'derniere_activite_at',
    ];

    protected $casts = [
        'service'              => ServiceDestinataireEnum::class,
        'service_origine'      => ServiceDestinataireEnum::class,
        'statut'               => StatutConversationEnum::class,
        'escaladee_at'         => 'datetime',
        'derniere_activite_at' => 'datetime',
    ];

    /**
     * Le statut a bien un defaut en base, mais celui-ci ne s'applique qu'a
     * l'insertion : l'instance renvoyee par create() garderait un statut nul
     * jusqu'a sa relecture. Or un evenement de diffusion est construit sur
     * cette instance-la, et lit le statut. Le defaut est donc redit ici.
     */
    protected $attributes = [
        'statut' => StatutConversationEnum::OUVERTE->value,
    ];

    protected $hidden = [
        'deleted_at',
    ];

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /** L'agent qui s'est declare en charge du fil. */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Le dernier message du fil, pour l'apercu dans la liste. HasOne + ofMany
     * plutot que messages()->limit(1) : ce dernier casse en eager-loading, ou
     * la limite s'applique a l'ensemble des fils charges, pas a chacun.
     */
    public function dernierMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('id');
    }

    /**
     * Les fils qu'un role donne a le droit de voir : ceux des services qu'il
     * traite. L'admin et le manager traitent tous les services, la portee est
     * donc totale pour eux — ce qui tombe naturellement de rolesTraitants().
     */
    public function scopeVisiblesParRole(Builder $query, string $role): Builder
    {
        $services = array_values(array_filter(
            ServiceDestinataireEnum::cases(),
            fn (ServiceDestinataireEnum $s) => in_array($role, $s->rolesTraitants(), true),
        ));

        return $query->whereIn(
            'service',
            array_column($services, 'value'),
        );
    }

    /** Les fils encore a traiter, par opposition aux fils clos. */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereIn('statut', [
            StatutConversationEnum::OUVERTE->value,
            StatutConversationEnum::EN_COURS->value,
            StatutConversationEnum::ESCALADEE->value,
        ]);
    }

    /** Un fil archive n'accepte plus de message. */
    public function estClos(): bool
    {
        return !$this->statut->accepteMessage();
    }
}
