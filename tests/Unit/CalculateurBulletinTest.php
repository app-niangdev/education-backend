<?php

namespace Tests\Unit;

use App\Enums\TypeEvaluationEnum;
use App\Models\Affectation;
use App\Models\ClasseMatiere;
use App\Models\Evaluation;
use App\Models\Matiere;
use App\Models\Note;
use App\Services\Bulletin\CalculateurBulletin;
use App\Services\Bulletin\LigneCalculee;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Le moteur de calcul est vérifié contre le bulletin papier de référence
 * fourni par l'établissement (1er semestre 2016-2017, classe 1e S2).
 *
 * Ce document fait foi : si l'un de ces tests casse, c'est le calcul qui est
 * faux, pas le test. Les valeurs attendues sont recopiées du scan, pas
 * dérivées du code.
 *
 * Aucun accès base : le calculateur est une classe pure, on lui passe des
 * modèles hydratés en mémoire.
 */
class CalculateurBulletinTest extends TestCase
{
    private const ELEVE_ID = 999;

    private CalculateurBulletin $calculateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculateur = new CalculateurBulletin();
    }

    /**
     * Le bulletin de référence, ligne à ligne.
     *
     * La colonne « Devoir » du scan est déjà une moyenne : on la reconstitue
     * avec deux devoirs dont la moyenne donne la valeur imprimée.
     *
     * @return array<int, array{0:string,1:int,2:?float,3:?float,4:?float,5:?float,6:?float,7:?string}>
     */
    private function bulletinDeReference(): array
    {
        return [
            // matière,           coef, devoir1, devoir2, compo, moyenne, moy×coef, appréciation
            ['Français',          3,    10.0,    11.0,    7.0,   8.75,    26.25,    'Insuffisant'],
            ['Mathématiques',     5,    8.0,     9.0,     9.0,   8.75,    43.75,    'Insuffisant'],
            ['Science,Physique',  6,    13.0,    14.0,    9.0,   11.25,   67.5,     'Moyen'],
            ['Anglais',           2,    10.5,    10.0,    8.0,   9.125,   18.25,    'Insuffisant'],
            ['Histo - Géo',       2,    13.0,    13.5,    13.5,  13.375,  26.75,    'A. Bien'],
            ['Espagnol',          4,    null,    null,    null,  null,    null,     null],
            ['S. V. T',           6,    10.5,    11.0,    9.5,   10.125,  60.75,    'Passable'],
            ['Ed - Civique',      4,    null,    null,    null,  null,    null,     null],
            ['E.P.S',             1,    12.0,    12.0,    null,  12.0,    12.0,     'A. Bien'],
        ];
    }

    public function test_il_reproduit_les_moyennes_du_bulletin_de_reference(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        foreach (array_values($this->bulletinDeReference()) as $index => $attendu) {
            [$nom, , , , , $moyenne, , ] = $attendu;
            $ligne = $lignes[$index + 1];

            if ($moyenne === null) {
                $this->assertNull($ligne->moyenne, "{$nom} devrait être non notée");
                continue;
            }

            $this->assertEqualsWithDelta($moyenne, $ligne->moyenne, 0.0001, "Moyenne de {$nom}");
        }
    }

    /**
     * Le cœur de la règle d'arrondi : « Moy x » se calcule sur la moyenne
     * exacte, jamais sur son affichage à deux décimales.
     *
     * Anglais vaut 9,125 et le bulletin porte 18,25. Arrondir d'abord
     * donnerait 9,13 × 2 = 18,26, et le total général serait faux.
     */
    public function test_moy_x_coef_se_calcule_sur_la_moyenne_exacte(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        // Les trois lignes du scan dont la moyenne a trois décimales.
        $this->assertEqualsWithDelta(18.25, $lignes[4]->moyXCoef, 0.0001, 'Anglais 9,125 × 2');
        $this->assertEqualsWithDelta(26.75, $lignes[5]->moyXCoef, 0.0001, 'Histo-Géo 13,375 × 2');
        $this->assertEqualsWithDelta(60.75, $lignes[7]->moyXCoef, 0.0001, 'S.V.T 10,125 × 6');
    }

    public function test_il_reproduit_les_totaux_du_bulletin_de_reference(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes   = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);
        $agregats = $this->calculateur->agregats($lignes);

        $this->assertSame(25, $agregats['total_coefficients'], 'Espagnol et Ed-Civique sont hors du total');
        $this->assertEqualsWithDelta(255.25, $agregats['total_points'], 0.0001);
        $this->assertEqualsWithDelta(10.21, $agregats['moyenne_generale'], 0.0001);
    }

    public function test_il_reproduit_les_appreciations_du_bulletin_de_reference(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        foreach (array_values($this->bulletinDeReference()) as $index => $attendu) {
            [$nom, , , , , , , $appreciation] = $attendu;

            $this->assertSame(
                $appreciation,
                $lignes[$index + 1]->appreciation()?->libelle(),
                "Appréciation de {$nom}"
            );
        }
    }

    public function test_une_matiere_sans_note_est_exclue_du_total_des_coefficients(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        $this->assertFalse($lignes[6]->notee, 'Espagnol');
        $this->assertFalse($lignes[8]->notee, 'Ed-Civique');
        $this->assertNull($lignes[6]->coefficient, 'Une matière non notée ne porte pas de coefficient');
        $this->assertNull($lignes[6]->appreciation(), 'Ni appréciation');
    }

    public function test_une_matiere_sans_composition_prend_la_moyenne_des_devoirs(): void
    {
        [$programme, $evaluations] = $this->construireDepuis($this->bulletinDeReference());
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        // E.P.S : 12 en devoir, pas de composition → 12, et non 6.
        $this->assertEqualsWithDelta(12.0, $lignes[9]->moyenne, 0.0001);
    }

    public function test_un_seul_devoir_saisi_fait_la_moyenne_sans_division_par_deux(): void
    {
        [$programme, $evaluations] = $this->construireDepuis([
            ['Test', 2, 14.0, null, null, null, null, null],
        ]);
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        $this->assertEqualsWithDelta(14.0, $lignes[1]->moyenne, 0.0001);
    }

    /** Décision de l'établissement : une absence vaut 0, elle n'est pas ignorée. */
    public function test_une_absence_compte_pour_zero(): void
    {
        [$programme, $evaluations] = $this->construireDepuis([
            ['Test', 2, 12.0, 'ABSENT', 8.0, null, null, null],
        ]);
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        // devoirs = (12 + 0) / 2 = 6 ; moyenne = (6 + 8) / 2 = 7
        $this->assertEqualsWithDelta(7.0, $lignes[1]->moyenne, 0.0001);
    }

    /** Une évaluation non saisie est ignorée — à la différence d'une absence. */
    public function test_une_evaluation_non_saisie_est_ignoree(): void
    {
        [$programme, $evaluations] = $this->construireDepuis([
            ['Test', 2, 12.0, null, 8.0, null, null, null],
        ]);
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        // devoirs = 12 (le second n'existe pas) ; moyenne = (12 + 8) / 2 = 10
        $this->assertEqualsWithDelta(10.0, $lignes[1]->moyenne, 0.0001);
    }

    public function test_les_notes_sont_ramenees_sur_vingt(): void
    {
        [$programme, $evaluations] = $this->construireDepuis([
            ['Test', 1, 7.0, null, null, null, null, null],
        ], bareme: 10);
        $lignes = $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, $evaluations);

        $this->assertEqualsWithDelta(14.0, $lignes[1]->moyenne, 0.0001, '7/10 vaut 14/20');
    }

    public function test_un_eleve_sans_aucune_note_na_ni_moyenne_ni_rang(): void
    {
        [$programme] = $this->construireDepuis([['Test', 2, null, null, null, null, null, null]]);
        $agregats = $this->calculateur->agregats(
            $this->calculateur->lignesPourEleve(self::ELEVE_ID, $programme, collect())
        );

        $this->assertSame(0, $agregats['total_coefficients']);
        $this->assertNull($agregats['total_points']);
        $this->assertNull($agregats['moyenne_generale']);
        $this->assertSame([], $this->calculateur->rangGeneral([self::ELEVE_ID => null]));
    }

    /** Classement « competition » : 1, 2, 2, 4 — le rang suivant saute. */
    public function test_les_ex_aequo_partagent_le_rang_et_le_suivant_saute(): void
    {
        $rangs = $this->calculateur->rangGeneral([
            1 => 15.0,
            2 => 12.5,
            3 => 12.5,
            4 => 9.0,
            5 => null,
        ]);

        $this->assertSame(1, $rangs[1]['rang']);
        $this->assertFalse($rangs[1]['ex_aequo']);

        $this->assertSame(2, $rangs[2]['rang']);
        $this->assertTrue($rangs[2]['ex_aequo']);
        $this->assertSame(2, $rangs[3]['rang']);
        $this->assertTrue($rangs[3]['ex_aequo']);

        $this->assertSame(4, $rangs[4]['rang'], 'Le rang 3 est sauté');

        $this->assertArrayNotHasKey(5, $rangs, 'Un élève sans moyenne n\'est pas classé');
    }

    /**
     * 10,125 n'est pas représentable exactement en flottant binaire et sa
     * valeur machine est inférieure : un round() naïf rendrait 10,12.
     */
    public function test_larrondi_resiste_a_limprecision_du_flottant(): void
    {
        $agregats = $this->calculateur->agregats([
            new LigneCalculee(1, 'X', 1, null, null, 10.125, 1, 10.125, true),
        ]);

        $this->assertEqualsWithDelta(10.13, $agregats['total_points'], 0.0001);
    }

    // ------------------------------------------------------------------
    // Construction du jeu de données en mémoire
    // ------------------------------------------------------------------

    /**
     * Hydrate un programme et des évaluations depuis la description tabulaire.
     * Une note à 'ABSENT' produit une note absente ; null, aucune note.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function construireDepuis(array $modele, int $bareme = 20): array
    {
        $programme   = collect();
        $evaluations = collect();
        $matiereId   = 0;

        foreach ($modele as [$nom, $coef, $devoir1, $devoir2, $composition, , , ]) {
            $matiereId++;

            $matiere     = new Matiere(['nom' => $nom]);
            $matiere->id = $matiereId;

            $classeMatiere             = new ClasseMatiere(['coefficient' => $coef]);
            $classeMatiere->id         = $matiereId;
            $classeMatiere->matiere_id = $matiereId;
            $classeMatiere->setRelation('matiere', $matiere);
            $programme->push($classeMatiere);

            $affectation                    = new Affectation();
            $affectation->id                = $matiereId;
            $affectation->classe_matiere_id = $matiereId;

            $epreuves = [
                [TypeEvaluationEnum::DEVOIR_1, $devoir1],
                [TypeEvaluationEnum::DEVOIR_2, $devoir2],
                [TypeEvaluationEnum::COMPOSITION, $composition],
            ];

            foreach ($epreuves as [$type, $valeur]) {
                if ($valeur === null) {
                    continue;
                }

                $evaluation     = new Evaluation(['type' => $type, 'bareme' => $bareme]);
                $evaluation->id = $evaluations->count() + 1;
                $evaluation->setRelation('affectation', $affectation);

                $estAbsent = $valeur === 'ABSENT';

                $note = new Note([
                    'valeur' => $estAbsent ? null : (float) $valeur,
                    'absent' => $estAbsent,
                ]);
                $note->eleve_id = self::ELEVE_ID;

                $evaluation->setRelation('notes', collect([$note]));
                $evaluations->push($evaluation);
            }
        }

        return [$programme, $evaluations];
    }
}
