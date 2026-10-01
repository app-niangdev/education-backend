<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Le code de verification d'une connexion en cours.
 *
 * Le code circule en clair dans ce message — c'est sa raison d'etre — mais
 * n'est jamais conserve ailleurs : la base n'en detient que le hachage.
 */
class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        /** Duree de validite affichee, en minutes. */
        public readonly int $validiteMinutes,
        /** TWO_FACTOR ou IP_PREVIOUSLY_BLOCKED : change le texte d'accroche. */
        public readonly string $raison,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Code de vérification — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login_otp',
            with: [
                'user'            => $this->user,
                'code'            => $this->code,
                'validiteMinutes' => $this->validiteMinutes,
                'raison'          => $this->raison,
            ],
        );
    }
}
