<?php

namespace App\Enums;

/**
 * Jours ouvrables de l'emploi du temps. La valeur `ordre` sert au tri
 * (lundi = 1) et le libelle a l'affichage.
 */
enum JourSemaineEnum: string
{
    case LUNDI    = 'LUNDI';
    case MARDI    = 'MARDI';
    case MERCREDI = 'MERCREDI';
    case JEUDI    = 'JEUDI';
    case VENDREDI = 'VENDREDI';
    case SAMEDI   = 'SAMEDI';

    public function ordre(): int
    {
        return match ($this) {
            self::LUNDI    => 1,
            self::MARDI    => 2,
            self::MERCREDI => 3,
            self::JEUDI    => 4,
            self::VENDREDI => 5,
            self::SAMEDI   => 6,
        };
    }

    public function libelle(): string
    {
        return ucfirst(strtolower($this->value));
    }

    /**
     * Le jour ouvrable correspondant a une date, pour retrouver les creneaux
     * a tenir ce jour-la.
     *
     * Retourne null le dimanche : c'est une date parfaitement valide sans
     * cours, pas une erreur — a l'appelant d'afficher « aucun cours ce jour ».
     */
    public static function fromDate(\DateTimeInterface $date): ?self
    {
        return match ((int) $date->format('N')) { // 1 = lundi ... 7 = dimanche
            1       => self::LUNDI,
            2       => self::MARDI,
            3       => self::MERCREDI,
            4       => self::JEUDI,
            5       => self::VENDREDI,
            6       => self::SAMEDI,
            default => null,
        };
    }
}
