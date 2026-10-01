<?php

namespace App\Services;

use App\Interfaces\LoginSecurityServiceInterface;
use App\Models\LoginSecurity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Le blocage progressif des adresses IP.
 *
 * Toute ecriture passe par une transaction avec verrou de ligne : cinq essais
 * lances en parallele ne doivent compter que pour cinq, et deux echecs
 * simultanes ne doivent pas poser deux blocages dont l'un ecraserait l'autre.
 * Sans le verrou, un attaquant obtiendrait par la concurrence ce que la regle
 * lui refuse.
 */
class LoginSecurityService implements LoginSecurityServiceInterface
{
    public function __construct(
        private readonly SecurityLogger $journal
    ) {}

    public function etat(string $ip): ?LoginSecurity
    {
        return LoginSecurity::query()->where('ip_address', $ip)->first();
    }

    public function enregistrerEchec(string $ip): LoginSecurity
    {
        return DB::transaction(function () use ($ip) {
            $etat = $this->verrouillerOuCreer($ip);

            // Garde-fou : le controleur a deja refuse les IP bloquees, mais
            // deux requetes parties ensemble peuvent franchir ce controle avant
            // que la premiere n'ait pose le blocage. La seconde s'arrete ici,
            // sinon elle prolongerait un blocage qu'elle n'a pas provoque.
            if ($etat->isBlocked()) {
                return $etat;
            }

            $etat->failed_attempts += 1;
            $etat->last_failed_at   = Carbon::now();

            $seuil = (int) config('login_security.max_attempts', 5);

            if ($etat->failed_attempts >= $seuil) {
                $etat->block_level  += 1;
                $duree               = $this->dureeBlocage($etat->block_level);
                $etat->blocked_until = Carbon::now()->addSeconds($duree);
                $etat->was_blocked   = true;

                $etat->save();

                $this->journal->log(SecurityLogger::IP_BLOCKED, null, [
                    'block_level'     => $etat->block_level,
                    'failed_attempts' => $etat->failed_attempts,
                    'blocked_until'   => $etat->blocked_until->toIso8601String(),
                    'duration'        => $duree,
                ]);

                return $etat;
            }

            $etat->save();

            $this->journal->log(SecurityLogger::LOGIN_FAILED, null, [
                'failed_attempts'   => $etat->failed_attempts,
                'attempts_left'     => $seuil - $etat->failed_attempts,
            ]);

            return $etat;
        });
    }

    public function reinitialiser(string $ip): void
    {
        DB::transaction(function () use ($ip) {
            $etat = $this->verrouillerOuCreer($ip);

            $etat->fill([
                'failed_attempts' => 0,
                'block_level'     => 0,
                'blocked_until'   => null,
                'was_blocked'     => false,
                'last_success_at' => Carbon::now(),
            ])->save();
        });
    }

    public function aEteBloquee(string $ip): bool
    {
        $etat = $this->etat($ip);

        return $etat !== null && $etat->was_blocked;
    }

    public function purger(int $joursInactivite): int
    {
        return LoginSecurity::query()
            ->where('updated_at', '<', Carbon::now()->subDays($joursInactivite))
            // Un blocage long — jusqu'a 24 h — ne doit pas disparaitre avec le
            // menage : ce serait un deblocage deguise.
            ->where(function ($q) {
                $q->whereNull('blocked_until')
                  ->orWhere('blocked_until', '<', Carbon::now());
            })
            ->delete();
    }

    /**
     * Duree du blocage pour un palier donne : 1 min x 2^(niveau - 1), plafonnee.
     *
     * Le decalage binaire est borne a 30 avant l'elevation : au-dela, sur une
     * plateforme 32 bits, il deborderait et rendrait une duree negative — donc
     * un blocage deja expire.
     */
    private function dureeBlocage(int $niveau): int
    {
        $base    = (int) config('login_security.base_block_seconds', 60);
        $plafond = (int) config('login_security.max_block_seconds', 86400);

        $exposant = max(0, min($niveau - 1, 30));
        $duree    = $base * (2 ** $exposant);

        return (int) min($duree, $plafond);
    }

    /**
     * La ligne de l'IP, verrouillee pour la duree de la transaction.
     *
     * `firstOrCreate` puis `lockForUpdate` laisserait passer deux creations
     * concurrentes ; on s'appuie donc sur l'index unique, en rattrapant la
     * collision par une relecture verrouillee.
     */
    private function verrouillerOuCreer(string $ip): LoginSecurity
    {
        $etat = LoginSecurity::query()
            ->where('ip_address', $ip)
            ->lockForUpdate()
            ->first();

        if ($etat) {
            return $etat;
        }

        try {
            return LoginSecurity::query()->create([
                'ip_address'      => $ip,
                'failed_attempts' => 0,
                'block_level'     => 0,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Une requete concurrente a cree la ligne entre-temps.
            return LoginSecurity::query()
                ->where('ip_address', $ip)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}
