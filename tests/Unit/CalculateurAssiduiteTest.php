<?php

namespace Tests\Unit;

use App\Interfaces\AssiduiteRepositoryInterface;
use App\Services\Assiduite\CalculateurAssiduite;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Les deux chiffres portés au bulletin.
 *
 * Le repository est doublé : ce qui est vérifié ici, c'est la conversion des
 * minutes en heures et la distinction null / zéro, pas les requêtes SQL.
 */
class CalculateurAssiduiteTest extends TestCase
{
    public function test_il_convertit_les_minutes_en_heures(): void
    {
        $calculateur = $this->calculateurAvec([
            (object) ['eleve_id' => 1, 'minutes' => 300, 'retards' => 0], // 5 h
            (object) ['eleve_id' => 2, 'minutes' => 60,  'retards' => 2], // 1 h
        ]);

        $resultat = $calculateur->pourClasseEtPeriode(1, 1);

        $this->assertSame(5, $resultat[1]['absences']);
        $this->assertSame(0, $resultat[1]['retards']);
        $this->assertSame(1, $resultat[2]['absences']);
        $this->assertSame(2, $resultat[2]['retards']);
    }

    /**
     * L'arrondi n'intervient qu'une fois, sur le total : arrondir séance par
     * séance ferait dériver le résultat.
     */
    public function test_larrondi_porte_sur_le_total_pas_sur_chaque_seance(): void
    {
        // 12 créneaux de 55 min = 660 min = 11 h exactement.
        $calculateur = $this->calculateurAvec([
            (object) ['eleve_id' => 1, 'minutes' => 660, 'retards' => 0],
        ]);

        $this->assertSame(11, $calculateur->pourClasseEtPeriode(1, 1)[1]['absences']);
    }

    public function test_il_arrondit_au_plus_proche(): void
    {
        $calculateur = $this->calculateurAvec([
            (object) ['eleve_id' => 1, 'minutes' => 100, 'retards' => 0], // 1,67 h
            (object) ['eleve_id' => 2, 'minutes' => 80,  'retards' => 0], // 1,33 h
        ]);

        $resultat = $calculateur->pourClasseEtPeriode(1, 1);

        $this->assertSame(2, $resultat[1]['absences']);
        $this->assertSame(1, $resultat[2]['absences']);
    }

    /**
     * Sans aucun appel, on ne sait rien : écrire 0 laisserait croire à une
     * assiduité parfaite. Le bulletin doit afficher « — ».
     */
    public function test_sans_aucun_appel_le_resultat_est_null_et_non_zero(): void
    {
        $calculateur = $this->calculateurSansSeance();

        $agregats = $calculateur->pourClasseEtPeriode(1, 1);
        $this->assertSame([], $agregats);

        $pourEleve = $calculateur->pourEleve($agregats, 1);
        $this->assertNull($pourEleve['absences']);
        $this->assertNull($pourEleve['retards']);
    }

    /** En revanche, un élève sans anomalie dans une classe appelée vaut 0. */
    public function test_un_eleve_sans_anomalie_vaut_zero_si_lappel_a_eu_lieu(): void
    {
        $calculateur = $this->calculateurAvec([
            (object) ['eleve_id' => 1, 'minutes' => 120, 'retards' => 0],
        ]);

        $agregats = $calculateur->pourClasseEtPeriode(1, 1);

        // L'élève 2 n'a aucune ligne : il était présent partout.
        $this->assertSame(['absences' => 0, 'retards' => 0], $calculateur->pourEleve($agregats, 2));
    }

    // ------------------------------------------------------------------

    /** @param array<int, object> $lignes */
    private function calculateurAvec(array $lignes): CalculateurAssiduite
    {
        $repository = $this->createMock(AssiduiteRepositoryInterface::class);
        $repository->method('existeSeanceFaite')->willReturn(true);
        $repository->method('agregatsPourBulletin')->willReturn(new Collection($lignes));

        return new CalculateurAssiduite($repository);
    }

    private function calculateurSansSeance(): CalculateurAssiduite
    {
        $repository = $this->createMock(AssiduiteRepositoryInterface::class);
        $repository->method('existeSeanceFaite')->willReturn(false);

        return new CalculateurAssiduite($repository);
    }
}
