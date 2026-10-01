<?php

namespace App\Enums;

/**
 * Ou en est la depense dans son circuit de validation.
 *
 * Une depense naît toujours EN_ATTENTE, quel que soit son auteur : c'est le
 * manager (ou l'admin) qui engage l'etablissement en la validant. Tant qu'elle
 * ne l'est pas, elle n'entre ni dans les totaux ni dans le bilan — une sortie
 * de caisse annoncee n'est pas une sortie de caisse consentie.
 *
 * REFUSEE conserve la trace du rejet et son motif, plutot que de supprimer la
 * ligne : le tresorier doit pouvoir comprendre ce qui a ete ecarte, et
 * corriger sa saisie le cas echeant.
 */
enum StatutDepenseEnum: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case VALIDEE    = 'VALIDEE';
    case REFUSEE    = 'REFUSEE';

    public function libelle(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::VALIDEE    => 'Validée',
            self::REFUSEE    => 'Refusée',
        };
    }

    /**
     * Seule une depense validee pese sur la tresorerie : c'est le seul statut
     * que les totaux et le bilan financier retiennent.
     */
    public function estComptabilisee(): bool
    {
        return $this === self::VALIDEE;
    }

    /**
     * Une depense encore en attente attend une decision : c'est la seule sur
     * laquelle valider ou refuser a un sens.
     */
    public function attendUneDecision(): bool
    {
        return $this === self::EN_ATTENTE;
    }

    /** Les valeurs, pour les regles de validation et les colonnes enum. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
