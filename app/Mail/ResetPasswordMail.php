<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Le lien de reinitialisation du mot de passe.
 *
 * Le lien porte le jeton et l'adresse : c'est lui, et non la connaissance du
 * mot de passe actuel, qui autorise le changement. Il ne vaut donc que peu de
 * temps et ne sert qu'une fois.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        /** URL complete vers l'ecran de reinitialisation du frontend. */
        public readonly string $resetUrl,
        /** Duree de validite affichee, en minutes. */
        public readonly int $validiteMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset_password',
            with: [
                'user'            => $this->user,
                'resetUrl'        => $this->resetUrl,
                'validiteMinutes' => $this->validiteMinutes,
            ],
        );
    }
}
