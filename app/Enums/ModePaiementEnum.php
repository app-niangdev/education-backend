<?php

namespace App\Enums;

enum ModePaiementEnum: string
{
    case ESPECES = 'ESPECES';
    case WAVE = 'WAVE';
    case ORANGE_MONEY = 'ORANGE_MONEY';
    case FREE_MONEY = 'FREE_MONEY';

    /** Libelle lisible : 'ORANGE_MONEY' ne s'affiche pas tel quel. */
    public function libelle(): string
    {
        return match ($this) {
            self::ESPECES      => 'Espèces',
            self::WAVE         => 'Wave',
            self::ORANGE_MONEY => 'Orange Money',
            self::FREE_MONEY   => 'Free Money',
        };
    }
}
