<?php

namespace App\Enums;

/**
 * L'appréciation portée sur une matière, colonne « Appréciations » du
 * bulletin. Les libellés reprennent littéralement ceux du bulletin papier
 * de l'établissement — d'où « A. Bien » et non « Assez Bien ».
 *
 * Les seuils ont été calés sur le bulletin de référence fourni par le
 * client et en reproduisent les sept lignes notées :
 *   8,75 → Insuffisant · 11,25 → Moyen · 9,125 → Insuffisant
 *   13,375 → A. Bien   · 10,125 → Passable · 12 → A. Bien
 */
enum AppreciationEnum: string
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
            self::ASSEZ_BIEN  => 'A. Bien',
            self::MOYEN       => 'Moyen',
            self::PASSABLE    => 'Passable',
            self::INSUFFISANT => 'Insuffisant',
            self::FAIBLE      => 'Faible',
        };
    }

    /**
     * L'appréciation correspondant à une moyenne sur 20.
     * Retourne null pour une matière non notée : le bulletin papier laisse
     * alors la cellule vide plutôt que d'y porter un jugement.
     */
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
