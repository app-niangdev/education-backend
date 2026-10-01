<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Le lien qui ouvre un compte fraichement cree.
 *
 * Aucun mot de passe n'y figure, et c'est tout l'interet : un mot de passe
 * envoye par courriel reste lisible dans la boite pour des annees, chez son
 * destinataire comme chez tous les intermediaires qu'il a traverses. Ce lien-ci
 * ne sert qu'une fois, et le mot de passe n'existe qu'entre son titulaire et
 * l'empreinte stockee en base.
 */
class ActivationCompteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        /** URL complete vers l'ecran de choix du mot de passe. */
        public readonly string $activationUrl,
        /** Duree de validite affichee, en heures. */
        public readonly int $validiteHeures,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activez votre compte — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.activation_compte',
            with: [
                'user'          => $this->user,
                'activationUrl' => $this->activationUrl,
                'validiteJours' => (int) ceil($this->validiteHeures / 24),
            ],
        );
    }
}
