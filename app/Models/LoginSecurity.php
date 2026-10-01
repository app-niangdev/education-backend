<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * L'etat de securite d'une IP face au formulaire de connexion.
 *
 * Le modele ne fait que lire son propre etat ; les transitions (echec, blocage,
 * reussite) appartiennent au LoginSecurityService, qui seul les ecrit et sous
 * verrou.
 */
class LoginSecurity extends Model
{
    protected $table = 'login_security';

    protected $fillable = [
        'ip_address',
        'failed_attempts',
        'block_level',
        'blocked_until',
        'was_blocked',
        'last_failed_at',
        'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked_until'   => 'datetime',
            'last_failed_at'  => 'datetime',
            'last_success_at' => 'datetime',
            'was_blocked'     => 'boolean',
        ];
    }

    /** Vrai tant que l'instant present precede la fin du blocage. */
    public function isBlocked(): bool
    {
        return $this->blocked_until !== null
            && $this->blocked_until->isFuture();
    }

    /** Secondes restantes avant la fin du blocage (0 si l'IP est libre). */
    public function retryAfter(): int
    {
        if (! $this->isBlocked()) {
            return 0;
        }

        // Un blocage a une seconde d'echeance ne doit pas s'annoncer comme
        // termine : on arrondit au superieur.
        return (int) ceil(Carbon::now()->diffInSeconds($this->blocked_until, false));
    }
}
