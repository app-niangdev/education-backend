<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Message envoye depuis le formulaire de contact de la page d'accueil.
 *
 * L'expediteur reste l'adresse technique de l'application : un serveur SMTP
 * refuse d'expedier au nom d'un domaine qu'il ne controle pas. L'adresse
 * saisie par le visiteur sert donc de replyTo — repondre depuis la boite de
 * l'etablissement ecrit bien au visiteur, pas a l'application.
 */
class ContactPubliqueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        /** Adresse saisie par le visiteur : c'est elle qui recoit la reponse. */
        public readonly string $expediteurEmail,
        public readonly string $titre,
        public readonly string $messageContact,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Contact site] ' . $this->titre,
            replyTo: [new Address($this->expediteurEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact_publique',
            with: [
                'expediteurEmail' => $this->expediteurEmail,
                'titre'           => $this->titre,
                'messageContact'  => $this->messageContact,
            ],
        );
    }
}
