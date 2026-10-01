<?php

namespace App\Enums;

/**
 * Bloc de gauche du bulletin papier : l'appréciation générale portée par le
 * conseil des professeurs. Une seule case est cochée.
 *
 * Les libellés reproduisent le bulletin de l'établissement, y compris son
 * orthographe (« Risque de Rédoubler ») : ce document est signé et diffusé
 * aux familles, sa forme fait partie de l'identité de l'école.
 */
enum DecisionConseilEnum: string
{
    case SATISFAISANT_DOIT_CONTINUER = 'SATISFAISANT_DOIT_CONTINUER';
    case PEUT_MIEUX_FAIRE            = 'PEUT_MIEUX_FAIRE';
    case INSUFFISANT                 = 'INSUFFISANT';
    case RISQUE_DE_REDOUBLER         = 'RISQUE_DE_REDOUBLER';
    case RISQUE_EXCLUSION            = 'RISQUE_EXCLUSION';

    public function libelle(): string
    {
        return match ($this) {
            self::SATISFAISANT_DOIT_CONTINUER => 'Satisfaisant doit continuer',
            self::PEUT_MIEUX_FAIRE            => 'Peut Mieux Faire',
            self::INSUFFISANT                 => 'Insuffisant',
            self::RISQUE_DE_REDOUBLER         => 'Risque de Rédoubler',
            self::RISQUE_EXCLUSION            => "Risque l'exclusion",
        };
    }
}
