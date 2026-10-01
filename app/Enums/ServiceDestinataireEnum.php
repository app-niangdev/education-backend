<?php

namespace App\Enums;

/**
 * Les guichets auxquels un tuteur adresse une conversation.
 *
 * Le tuteur ecrit a un service, jamais a une personne : un agent absent ne
 * doit pas laisser un message sans reponse, n'importe quel membre du service
 * reprend le fil.
 *
 * DIRECTION ne figure pas dans les guichets ouverts a la saisie (voir
 * ouvertsAuTuteur()) : on ne l'atteint que par escalade, quand la scolarite ou
 * la tresorerie ne peut pas trancher. Cela evite que la direction devienne le
 * premier reflexe et court-circuite les services competents.
 */
enum ServiceDestinataireEnum: string
{
    case SCOLARITE  = 'SCOLARITE';
    case TRESORERIE = 'TRESORERIE';
    case DIRECTION  = 'DIRECTION';

    public function libelle(): string
    {
        return match ($this) {
            self::SCOLARITE  => 'Scolarité & surveillance',
            self::TRESORERIE => 'Trésorerie',
            self::DIRECTION  => 'Direction',
        };
    }

    /**
     * Les roles qui traitent les conversations de ce service. Sert a lister
     * les fils d'un agent et a decider qui a le droit de repondre.
     *
     * L'admin et le manager voient tout : ils supervisent l'etablissement et
     * doivent pouvoir reprendre un fil laisse en souffrance dans n'importe
     * quel guichet.
     */
    public function rolesTraitants(): array
    {
        return match ($this) {
            self::SCOLARITE  => ['admin', 'manager', 'supervisor'],
            self::TRESORERIE => ['admin', 'manager', 'treasurer'],
            self::DIRECTION  => ['admin', 'manager'],
        };
    }

    /**
     * Les guichets qu'un tuteur peut choisir lui-meme a l'ouverture d'un fil.
     * La direction en est exclue : elle s'atteint par escalade.
     */
    public static function ouvertsAuTuteur(): array
    {
        return [self::SCOLARITE, self::TRESORERIE];
    }

    /** Vrai si un tuteur peut adresser directement une conversation ici. */
    public function ouvertAuTuteur(): bool
    {
        return in_array($this, self::ouvertsAuTuteur(), true);
    }

    /** Les valeurs, pour les regles de validation et les colonnes enum. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
