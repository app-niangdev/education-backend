<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une authentification en attente de son code de verification.
 *
 * Le challenge est consommable une seule fois : une fois `verified_at` pose,
 * il ne peut plus rien ouvrir.
 */
class LoginChallenge extends Model
{
    protected $fillable = [
        'challenge_token',
        'user_id',
        'ip_address',
        'otp_hash',
        'reason',
        'expires_at',
        'attempts',
        'resend_count',
        'last_sent_at',
        'verified_at',
    ];

    protected $hidden = [
        'otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'verified_at'  => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Utilisable : ni deja consomme, ni perime. */
    public function isPending(): bool
    {
        return ! $this->isVerified() && ! $this->isExpired();
    }
}
