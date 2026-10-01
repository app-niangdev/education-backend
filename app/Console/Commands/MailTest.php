<?php

namespace App\Console\Commands;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Verifie que les e-mails partent vraiment.
 *
 * Le pire defaut de configuration est silencieux : avec le mailer `log`, tout
 * semble fonctionner — l'application ne signale aucune erreur — mais les codes
 * de verification s'ecrivent dans un fichier au lieu d'arriver a leur
 * destinataire. Cette commande rend ce cas visible avant qu'il ne ferme l'acces
 * a un utilisateur.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {email? : Adresse de destination}';

    protected $description = 'Affiche la configuration mail et envoie un message de test';

    public function handle(): int
    {
        $mailer = config('mail.default');

        $this->newLine();
        $this->line('<comment>Configuration active</comment>');
        $this->table(['Paramètre', 'Valeur'], [
            ['mailer', $mailer],
            ['host', config("mail.mailers.{$mailer}.host") ?? '—'],
            ['port', config("mail.mailers.{$mailer}.port") ?? '—'],
            ['username', config("mail.mailers.{$mailer}.username") ?: '—'],
            ['from', config('mail.from.address')],
        ]);

        if ($mailer === 'log') {
            $this->warn('Le mailer est « log » : aucun e-mail ne part réellement.');
            $this->line('  Les messages sont écrits dans storage/logs/laravel.log.');
            $this->line('  Renseignez MAIL_MAILER=smtp dans .env pour un envoi réel.');
            $this->newLine();
        }

        $destination = $this->argument('email')
            ?? $this->ask('Adresse de destination', 'test@yopmail.com');

        // Le message de test emprunte le gabarit reel des codes de connexion :
        // valider un autre message ne dirait rien de celui qui compte.
        $utilisateur = User::whereNotNull('email')->first()
            ?? new User(['first_name' => 'Test', 'last_name' => 'Diagnostic', 'email' => $destination]);

        $this->line("Envoi vers <info>{$destination}</info>…");

        try {
            Mail::to($destination)->send(
                new LoginOtpMail($utilisateur, '123456', 5, 'TWO_FACTOR')
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Échec de l\'envoi : ' . $e->getMessage());
            $this->line('Vérifiez MAIL_HOST, MAIL_PORT et vos identifiants dans .env,');
            $this->line('puis relancez « php artisan config:clear ».');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Message accepté par le transporteur.');

        if ($mailer === 'log') {
            $this->line('→ À lire dans storage/logs/laravel.log (code attendu : 123456).');
        } else {
            $this->line('→ Vérifiez la boîte de réception du destinataire.');
        }

        return self::SUCCESS;
    }
}
