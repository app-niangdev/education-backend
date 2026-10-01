<?php

namespace App\Enums;

/**
 * Les postes de depense d'un etablissement scolaire.
 *
 * Liste fermee : elle sert d'axe d'analyse (repartition des depenses par
 * poste), ce qu'une saisie libre rendrait impossible. AUTRES accueille ce qui
 * n'entre dans aucun poste, le libelle de la depense precisant alors la nature.
 */
enum CategorieDepenseEnum: string
{
    case SALAIRES        = 'SALAIRES';
    case FOURNITURES     = 'FOURNITURES';
    case LOYER           = 'LOYER';
    case ELECTRICITE_EAU = 'ELECTRICITE_EAU';
    case TRANSPORT       = 'TRANSPORT';
    case MAINTENANCE     = 'MAINTENANCE';
    case AUTRES          = 'AUTRES';

    public function libelle(): string
    {
        return match ($this) {
            self::SALAIRES        => 'Salaires',
            self::FOURNITURES     => 'Fournitures',
            self::LOYER           => 'Loyer',
            self::ELECTRICITE_EAU => 'Électricité & eau',
            self::TRANSPORT       => 'Transport',
            self::MAINTENANCE     => 'Maintenance',
            self::AUTRES          => 'Autres',
        };
    }

    /** Les valeurs, pour les regles de validation et les colonnes enum. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
