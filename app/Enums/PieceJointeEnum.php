<?php

namespace App\Enums;

/**
 * Ce qu'un message peut porter comme justificatif.
 *
 * Le fichier n'est jamais stocke : la valeur dit COMMENT le regenerer, et
 * `piece_jointe_id` sur quel objet. Voir la migration
 * add_piece_jointe_to_messages_table pour le raisonnement.
 */
enum PieceJointeEnum: string
{
    /** Versement sur les frais d'inscription (PaiementInscription). */
    case RECU_INSCRIPTION = 'RECU_INSCRIPTION';

    /** Versement sur une mensualite (PaiementMensualite). */
    case RECU_MENSUALITE = 'RECU_MENSUALITE';

    /** Reglement de plusieurs mois sous un numero unique (FactureMensualite). */
    case FACTURE_MENSUALITES = 'FACTURE_MENSUALITES';

    public function libelle(): string
    {
        return match ($this) {
            self::RECU_INSCRIPTION    => "Reçu d'inscription",
            self::RECU_MENSUALITE     => 'Reçu de mensualité',
            self::FACTURE_MENSUALITES => 'Facture de mensualités',
        };
    }

    /** Les valeurs, pour les regles de validation. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
