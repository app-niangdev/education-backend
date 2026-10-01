<?php

namespace App\Enums;

/**
 * La mention attachée à la moyenne générale du bulletin.
 *
 * Volontairement distincte d'AppreciationEnum, bien que les seuils soient
 * aujourd'hui identiques : l'appréciation qualifie une matière, la mention
 * qualifie l'élève sur l'ensemble de la période. Les deux notions
 * divergeront au lot 2 (décision annuelle de passage).
 *
 * Le bulletin papier ne comporte pas de mention : elle n'est pas imprimée
 * sur le PDF et sert uniquement à l'affichage et au tri à l'écran.
 */
enum MentionEnum: string
{
    case TRES_BIEN   = 'TRES_BIEN';
    case BIEN        = 'BIEN';
    case ASSEZ_BIEN  = 'ASSEZ_BIEN';
    case MOYEN       = 'MOYEN';
    case PASSABLE    = 'PASSABLE';
    case INSUFFISANT = 'INSUFFISANT';
    case FAIBLE      = 'FAIBLE';

    public function libelle(): string
    {
        return match ($this) {
            self::TRES_BIEN   => 'Très Bien',
            self::BIEN        => 'Bien',
            self::ASSEZ_BIEN  => 'Assez Bien',
            self::MOYEN       => 'Moyen',
            self::PASSABLE    => 'Passable',
            self::INSUFFISANT => 'Insuffisant',
            self::FAIBLE      => 'Faible',
        };
    }

    /** La mention correspondant à une moyenne générale sur 20. */
    public static function pourMoyenne(?float $moyenne): ?self
    {
        if ($moyenne === null) {
            return null;
        }

        return match (true) {
            $moyenne >= 16 => self::TRES_BIEN,
            $moyenne >= 14 => self::BIEN,
            $moyenne >= 12 => self::ASSEZ_BIEN,
            $moyenne >= 11 => self::MOYEN,
            $moyenne >= 10 => self::PASSABLE,
            $moyenne >= 5  => self::INSUFFISANT,
            default        => self::FAIBLE,
        };
    }
}
