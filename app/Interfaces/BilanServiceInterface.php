<?php

namespace App\Interfaces;

use App\Models\AnneeScolaire;

interface BilanServiceInterface
{
    /**
     * Bilan financier d'une annee scolaire (celle en cours par defaut),
     * eventuellement restreint a un mois civil.
     *
     * Le perimetre mensuel ne s'applique qu'aux flux dates — encaissements,
     * depenses, resultat — car eux seuls appartiennent a un mois. Les creances
     * (ce que les eleves doivent encore) restent cumulees sur l'annee : un
     * impaye ne cesse pas d'exister parce qu'on regarde un autre mois.
     *
     * @param  int|null  $mois         Mois civil 1-12, ou null pour toute l'annee.
     * @param  int|null  $anneeCivile  Annee civile du mois vise ; requise avec
     *                                 $mois, une annee scolaire chevauchant
     *                                 deux millesimes.
     * @return array{
     *     annee_scolaire: array|null,
     *     periode: array,
     *     mois_disponibles: array,
     *     resultat: array,
     *     encaissements: array,
     *     depenses: array,
     *     masse_salariale: array,
     *     creances: array,
     *     evolution_mensuelle: array
     * }
     */
    public function bilan(?AnneeScolaire $annee = null, ?int $mois = null, ?int $anneeCivile = null): array;
}
