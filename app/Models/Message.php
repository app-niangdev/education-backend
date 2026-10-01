<?php

namespace App\Models;

use App\Enums\PieceJointeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un message dans un fil. L'expediteur est une personne (tuteur ou agent) ;
 * le service, lui, est porte par la conversation.
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'expediteur_id',
        'corps',
        'est_systeme',
        'piece_jointe_type',
        'piece_jointe_id',
        'piece_jointe_libelle',
    ];

    protected $casts = [
        'est_systeme'       => 'boolean',
        'piece_jointe_type' => PieceJointeEnum::class,
    ];

    protected $appends = [
        'a_piece_jointe',
    ];

    /**
     * Evite au client d'avoir a tester deux colonnes pour savoir s'il doit
     * afficher un bouton de telechargement.
     */
    public function getAPieceJointeAttribute(): bool
    {
        return $this->piece_jointe_type !== null && $this->piece_jointe_id !== null;
    }

    protected $hidden = [
        'deleted_at',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }
}
