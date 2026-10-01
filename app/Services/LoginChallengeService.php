<?php

namespace App\Services;

use App\Interfaces\LoginChallengeServiceInterface;
use App\Mail\LoginOtpMail;
use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Les codes de verification a usage unique.
 *
 * Le code n'existe en clair que le temps de partir par e-mail. Ce qui reste en
 * base est son hachage : la table ne permet donc pas de terminer une connexion,
 * meme lue integralement.
 */
class LoginChallengeService implements LoginChallengeServiceInterface
{
    public const OTP_VERIFIED          = 'OTP_VERIFIED';
    public const INVALID_OTP           = 'INVALID_OTP';
    public const OTP_EXPIRED           = 'OTP_EXPIRED';
    public const OTP_TOO_MANY_ATTEMPTS = 'OTP_TOO_MANY_ATTEMPTS';
    public const OTP_SENT              = 'OTP_SENT';
    public const OTP_RESEND_LIMIT      = 'OTP_RESEND_LIMIT';
    public const OTP_RESEND_COOLDOWN   = 'OTP_RESEND_COOLDOWN';

    public function __construct(
        private readonly SecurityLogger $journal
    ) {}

    public function ouvrir(User $user, string $ip, string $raison): LoginChallenge
    {
        $code = $this->genererCode();
        $ttl  = (int) config('login_security.otp.ttl', 300);

        $challenge = DB::transaction(function () use ($user, $ip, $raison, $code, $ttl) {
            // Un seul code valable a la fois : les challenges laisses ouverts
            // par des connexions abandonnees sont perimes sur-le-champ, sans
            // quoi chaque tentative ajouterait une cle de plus a la serrure.
            LoginChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('verified_at')
                ->where('expires_at', '>', Carbon::now())
                ->update(['expires_at' => Carbon::now()]);

            return LoginChallenge::query()->create([
                'challenge_token' => $this->genererJeton(),
                'user_id'         => $user->id,
                'ip_address'      => $ip,
                'otp_hash'        => Hash::make($code),
                'reason'          => $raison,
                'expires_at'      => Carbon::now()->addSeconds($ttl),
                'attempts'        => 0,
                'resend_count'    => 0,
                'last_sent_at'    => Carbon::now(),
            ]);
        });

        $this->envoyer($user, $code, $ttl, $raison);

        $this->journal->log(SecurityLogger::OTP_SENT, $user->id, [
            'challenge_id' => $challenge->id,
            'reason'       => $raison,
            'expires_at'   => $challenge->expires_at->toIso8601String(),
        ]);

        return $challenge;
    }

    public function retrouver(string $token, string $ip): ?LoginChallenge
    {
        $challenge = LoginChallenge::query()
            ->where('challenge_token', $token)
            // Le code doit etre saisi depuis le poste qui a fourni le mot de
            // passe : un jeton intercepte ne s'utilise pas ailleurs.
            ->where('ip_address', $ip)
            ->whereNull('verified_at')
            ->first();

        return $challenge;
    }

    public function verifier(LoginChallenge $challenge, string $code): string
    {
        // Le plafond precede l'expiration : un challenge sature reste sature,
        // et l'annoncer « expire » suggererait a tort qu'un renvoi suffirait.
        $plafond = (int) config('login_security.otp.max_attempts', 5);

        if ($challenge->attempts >= $plafond) {
            return self::OTP_TOO_MANY_ATTEMPTS;
        }

        if ($challenge->isExpired()) {
            return self::OTP_EXPIRED;
        }

        // L'essai se compte avant la comparaison : une reponse perdue en route
        // ne doit pas offrir un essai gratuit.
        $challenge->increment('attempts');
        $challenge->refresh();

        if (! Hash::check($code, $challenge->otp_hash)) {
            $this->journal->log(SecurityLogger::OTP_VERIFICATION_FAILED, $challenge->user_id, [
                'challenge_id'  => $challenge->id,
                'attempts'      => $challenge->attempts,
                'attempts_left' => max(0, $plafond - $challenge->attempts),
            ]);

            return $challenge->attempts >= $plafond
                ? self::OTP_TOO_MANY_ATTEMPTS
                : self::INVALID_OTP;
        }

        $challenge->forceFill(['verified_at' => Carbon::now()])->save();

        $this->journal->log(SecurityLogger::OTP_VERIFIED, $challenge->user_id, [
            'challenge_id' => $challenge->id,
        ]);

        return self::OTP_VERIFIED;
    }

    public function renvoyer(LoginChallenge $challenge): string
    {
        if ($challenge->isExpired()) {
            return self::OTP_EXPIRED;
        }

        $maxRenvois = (int) config('login_security.otp.max_resend', 3);

        if ($challenge->resend_count >= $maxRenvois) {
            return self::OTP_RESEND_LIMIT;
        }

        $delai = (int) config('login_security.otp.resend_cooldown', 60);

        if ($challenge->last_sent_at && $challenge->last_sent_at->addSeconds($delai)->isFuture()) {
            return self::OTP_RESEND_COOLDOWN;
        }

        $code = $this->genererCode();
        $ttl  = (int) config('login_security.otp.ttl', 300);

        // Le renvoi remplace le code precedent, qui cesse aussitot de valoir.
        // La fenetre de validite repart, mais pas le compteur d'essais : sinon
        // le plafond de saisies se contournerait en redemandant un code.
        $challenge->forceFill([
            'otp_hash'     => Hash::make($code),
            'expires_at'   => Carbon::now()->addSeconds($ttl),
            'resend_count' => $challenge->resend_count + 1,
            'last_sent_at' => Carbon::now(),
        ])->save();

        $this->envoyer($challenge->user, $code, $ttl, $challenge->reason);

        $this->journal->log(SecurityLogger::OTP_SENT, $challenge->user_id, [
            'challenge_id' => $challenge->id,
            'reason'       => $challenge->reason,
            'resend_count' => $challenge->resend_count,
        ]);

        return self::OTP_SENT;
    }

    public function purger(int $jours): int
    {
        $limite = Carbon::now()->subDays($jours);

        return LoginChallenge::query()
            ->where('created_at', '<', $limite)
            ->where(function ($q) {
                $q->whereNotNull('verified_at')
                  ->orWhere('expires_at', '<', Carbon::now());
            })
            ->delete();
    }

    /**
     * Six chiffres tires du generateur cryptographique.
     *
     * `random_int` et non `rand` : la suite produite par ce dernier se devine a
     * partir de quelques tirages, ce qui suffirait a anticiper un code.
     */
    private function genererCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function genererJeton(): string
    {
        return Str::random(64);
    }

    /**
     * Achemine le code vers l'utilisateur.
     *
     * L'envoi est immediat et non differe en file : le code ne vaut que cinq
     * minutes, et un travailleur de file arrete le ferait arriver perime — ou
     * jamais. L'echec d'envoi n'interrompt pas la connexion en cours : le
     * challenge reste ouvert et l'utilisateur peut demander un renvoi.
     */
    private function envoyer(User $user, string $code, int $ttl, string $raison): void
    {
        if (! $user->email) {
            Log::channel('security')->warning('OTP non distribuable : utilisateur sans e-mail', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            Mail::to($user->email)->send(
                new LoginOtpMail($user, $code, (int) ceil($ttl / 60), $raison)
            );
        } catch (\Throwable $e) {
            // Le message d'erreur du transporteur peut contenir le contenu du
            // courriel : on ne journalise que sa classe.
            Log::channel('security')->error('Echec d\'envoi du code de verification', [
                'user_id'   => $user->id,
                'exception' => $e::class,
            ]);
        }
    }
}
