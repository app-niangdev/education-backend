<?php

namespace App\Services\Assiduite;

use App\Interfaces\AssiduiteRepositoryInterface;

/**
 * Convertit l'assiduité relevée en les deux chiffres du bulletin.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LES RÈGLES, telles qu'arbitrées par l'établissement
 * ────────────────────────────────────────────────────────────────────────
 *
 * 1. `absences` compte des HEURES, pas des séances. C'est la convention du
 *    bulletin papier, et c'est ce qui justifie l'appel par créneau : trois
 *    absences d'une heure et trois de quatre heures ne se valent pas.
 * 2. `retards` compte des OCCURRENCES. Un retard est un manquement ponctuel,
 *    sa durée n'a pas de portée horaire.
 * 3. Un élève RENVOYÉ n'a pas suivi l'heure : son absence compte comme telle.
 * 4. Le total inclut les absences justifiées — une heure manquée reste
 *    manquée. Le détail justifié/non justifié vit dans la fiche élève.
 * 5. Seules les séances effectivement tenues comptent : un cours non assuré
 *    ne pénalise personne.
 *
 * L'arrondi n'intervient qu'une seule fois, à la toute fin : arrondir séance
 * par séance ferait dériver le total (douze créneaux de 55 min font 11 h,
 * pas 12).
 */
final class CalculateurAssiduite
{
    private const MINUTES_PAR_HEURE = 60;

    public function __construct(
        private readonly AssiduiteRepositoryInterface $repository,
    ) {}

    /**
     * Les deux chiffres du bulletin, pour toute une classe et une période.
     *
     * Retourne un tableau vide si aucun appel n'a jamais été fait : le
     * bulletin laissera alors « — » plutôt que d'afficher un 0 mensonger,
     * qui laisserait croire à une assiduité parfaite.
     *
     * @return array<int, array{absences:int, retards:int}> indexé par eleve_id
     */
    public function pourClasseEtPeriode(int $classeId, int $periodeId): array
    {
        if (!$this->repository->existeSeanceFaite($classeId, $periodeId)) {
            return [];
        }

        $resultat = [];

        foreach ($this->repository->agregatsPourBulletin($classeId, $periodeId) as $ligne) {
            $resultat[(int) $ligne->eleve_id] = [
                'absences' => $this->minutesEnHeures((int) $ligne->minutes),
                'retards'  => (int) $ligne->retards,
            ];
        }

        return $resultat;
    }

    /**
     * Le chiffre à porter au bulletin pour un élève.
     *
     * `$agregats` vide signifie « aucun appel sur la période » : on renvoie
     * null pour que le bulletin affiche « — ». Un élève sans anomalie dans
     * une classe où l'appel est fait vaut bien 0, lui.
     *
     * @param  array<int, array{absences:int, retards:int}>  $agregats
     * @return array{absences:?int, retards:?int}
     */
    public function pourEleve(array $agregats, int $eleveId): array
    {
        if ($agregats === []) {
            return ['absences' => null, 'retards' => null];
        }

        return $agregats[$eleveId] ?? ['absences' => 0, 'retards' => 0];
    }

    private function minutesEnHeures(int $minutes): int
    {
        return (int) round($minutes / self::MINUTES_PAR_HEURE);
    }
}
