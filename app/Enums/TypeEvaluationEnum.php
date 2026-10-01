<?php

namespace App\Enums;

/**
 * Le cadre d'évaluation imposé : pour chaque matière et chaque période
 * (trimestre ou semestre), on attend exactement deux devoirs et une
 * composition. Un même type ne peut donc exister qu'une fois par
 * (matière × période) — l'unicité est garantie côté service.
 */
enum TypeEvaluationEnum: string
{
    case DEVOIR_1     = 'DEVOIR_1';
    case DEVOIR_2     = 'DEVOIR_2';
    case COMPOSITION  = 'COMPOSITION';

    public function libelle(): string
    {
        return match ($this) {
            self::DEVOIR_1    => 'Devoir 1',
            self::DEVOIR_2    => 'Devoir 2',
            self::COMPOSITION => 'Composition',
        };
    }
}
