<?php

namespace App\Services\Bulletin;

use App\Enums\MentionEnum;
use App\Enums\TypeEvaluationEnum;
use App\Models\ClasseMatiere;
use App\Models\Evaluation;
use Illuminate\Support\Collection;

/**
 * Le moteur de calcul des bulletins.
 *
 * Classe pure : aucun accès à la base, aucune dépendance injectée. Tout ce
 * dont elle a besoin lui est passé en paramètre. C'est ce qui la rend
 * vérifiable directement contre le bulletin papier de référence, sans monter
 * de jeu de données. Elle n'a donc pas d'interface : il n'y a rien à
 * substituer, et la testabilité ne vient pas d'une abstraction ici.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LES RÈGLES, telles que validées sur le bulletin de référence du client
 * ────────────────────────────────────────────────────────────────────────
 *
 * 1. Chaque note est ramenée sur 20 (le barème est propre à l'évaluation).
 * 2. Une absence compte pour 0 — décision de l'établissement.
 * 3. moy_devoirs = moyenne des DEVOIR_1 et DEVOIR_2 saisis. Un seul devoir
 *    saisi ? C'est lui la moyenne, sans division par deux.
 * 4. moyenne = (moy_devoirs + composition) / 2, et l'un des deux seul si
 *    l'autre manque. Ni l'un ni l'autre : matière non notée.
 * 5. Une matière non notée est imprimée en tirets et sort du total des
 *    coefficients (sur le modèle : Espagnol et Ed-Civique, d'où un total de
 *    25 et non 33).
 * 6. moy_x_coef se calcule sur la moyenne EXACTE, jamais sur son affichage.
 *    Anglais vaut 9,125 : 9,125 × 2 = 18,25, ce que porte le bulletin.
 *    Arrondir d'abord donnerait 9,13 × 2 = 18,26, et le total serait faux.
 *    Même démonstration pour Histo-Géo (13,375 × 2 = 26,75) et S.V.T
 *    (10,125 × 6 = 60,75).
 * 7. Les rangs se départagent sur la moyenne AFFICHÉE (2 décimales) : deux
 *    élèves que le bulletin montre à 12,50 doivent avoir le même rang.
 */
final class CalculateurBulletin
{
    /** Le barème de référence : toutes les notes y sont ramenées. */
    private const BAREME_REFERENCE = 20;

    /**
     * Les lignes d'un élève, une par matière du programme.
     *
     * @param  Collection<int, ClasseMatiere>  $programme    matières de la classe, avec `matiere` chargée
     * @param  Collection<int, Evaluation>     $evaluations  évaluations de la période, avec `notes` chargées
     * @return array<int, LigneCalculee>                     indexé par matiere_id
     */
    public function lignesPourEleve(int $eleveId, Collection $programme, Collection $evaluations): array
    {
        // Les évaluations regroupées par matière, via l'affectation.
        // Une affectation par couple (classe × matière) : le lien est direct.
        $parClasseMatiere = $evaluations->groupBy(
            fn (Evaluation $e) => (int) ($e->affectation?->classe_matiere_id ?? 0)
        );

        $lignes = [];
        $ordre  = 0;

        foreach ($programme as $classeMatiere) {
            $matiereId  = $classeMatiere->matiere_id !== null ? (int) $classeMatiere->matiere_id : null;
            $matiereNom = $classeMatiere->matiere?->nom ?? 'Matière supprimée';
            $ordre++;

            $evalsMatiere = $parClasseMatiere->get((int) $classeMatiere->id, collect());

            $moyDevoirs  = $this->moyenneDesDevoirs($evalsMatiere, $eleveId);
            $composition = $this->noteDeComposition($evalsMatiere, $eleveId);
            $moyenne     = $this->combiner($moyDevoirs, $composition);

            if ($moyenne === null) {
                $lignes[$matiereId ?? -$ordre] = LigneCalculee::nonNotee($matiereId, $matiereNom, $ordre);
                continue;
            }

            $coefficient = (int) ($classeMatiere->coefficient ?? 1);

            $lignes[$matiereId ?? -$ordre] = new LigneCalculee(
                matiereId:   $matiereId,
                matiereNom:  $matiereNom,
                ordre:       $ordre,
                moyDevoirs:  $moyDevoirs,
                composition: $composition,
                moyenne:     $moyenne,
                coefficient: $coefficient,
                // Sur la moyenne exacte : voir la règle 6 en tête de classe.
                moyXCoef:    $moyenne * $coefficient,
                notee:       true,
            );
        }

        return $lignes;
    }

    /**
     * Les totaux d'un élève à partir de ses lignes.
     *
     * @param  array<int, LigneCalculee>  $lignes
     * @return array{total_coefficients:int, total_points:?float, moyenne_generale:?float}
     */
    public function agregats(array $lignes): array
    {
        $totalCoefficients = 0;
        $totalPoints       = 0.0;
        $aDesNotes         = false;

        foreach ($lignes as $ligne) {
            if (!$ligne->notee) {
                continue; // règle 5 : hors du total
            }

            $aDesNotes          = true;
            $totalCoefficients += (int) $ligne->coefficient;
            $totalPoints       += (float) $ligne->moyXCoef;
        }

        if (!$aDesNotes || $totalCoefficients === 0) {
            return [
                'total_coefficients' => 0,
                'total_points'       => null,
                'moyenne_generale'   => null,
            ];
        }

        return [
            'total_coefficients' => $totalCoefficients,
            'total_points'       => $this->arrondir($totalPoints, 2),
            'moyenne_generale'   => $this->arrondir($totalPoints / $totalCoefficients, 2),
        ];
    }

    /**
     * Les rangs de chaque élève dans chaque matière.
     *
     * @param  array<int, array<int, LigneCalculee>>  $lignesParEleve  [eleve_id => [matiere_id => ligne]]
     * @return array<int, array<int, array{rang:int, ex_aequo:bool}>>  [eleve_id => [matiere_id => …]]
     */
    public function rangsParMatiere(array $lignesParEleve): array
    {
        // Regrouper les moyennes par matière, en ignorant les non-notés :
        // on ne classe pas un élève sur une matière qu'il n'a pas passée.
        $moyennesParMatiere = [];

        foreach ($lignesParEleve as $eleveId => $lignes) {
            foreach ($lignes as $matiereId => $ligne) {
                if ($ligne->notee) {
                    $moyennesParMatiere[$matiereId][$eleveId] = $ligne->moyenneAffichee();
                }
            }
        }

        $resultat = [];

        foreach ($moyennesParMatiere as $matiereId => $moyennes) {
            foreach ($this->classer($moyennes) as $eleveId => $rang) {
                $resultat[$eleveId][$matiereId] = $rang;
            }
        }

        return $resultat;
    }

    /**
     * Le rang général de chaque élève.
     *
     * @param  array<int, float|null>  $moyennesParEleve
     * @return array<int, array{rang:int, ex_aequo:bool}>
     */
    public function rangGeneral(array $moyennesParEleve): array
    {
        return $this->classer($moyennesParEleve);
    }

    public function mentionPour(?float $moyenneGenerale): ?MentionEnum
    {
        return MentionEnum::pourMoyenne($moyenneGenerale);
    }

    // ------------------------------------------------------------------
    // Calcul d'une matière
    // ------------------------------------------------------------------

    /** La moyenne des devoirs saisis, ou null si aucun ne l'est. */
    private function moyenneDesDevoirs(Collection $evaluations, int $eleveId): ?float
    {
        $notes = $evaluations
            ->filter(fn (Evaluation $e) => in_array(
                $e->type,
                [TypeEvaluationEnum::DEVOIR_1, TypeEvaluationEnum::DEVOIR_2],
                true,
            ))
            ->map(fn (Evaluation $e) => $this->noteSur20($e, $eleveId))
            ->filter(fn (?float $n) => $n !== null)
            ->values();

        if ($notes->isEmpty()) {
            return null;
        }

        // Un seul devoir saisi : c'est lui la moyenne (pas de division par 2).
        return (float) $notes->sum() / $notes->count();
    }

    private function noteDeComposition(Collection $evaluations, int $eleveId): ?float
    {
        $composition = $evaluations->first(
            fn (Evaluation $e) => $e->type === TypeEvaluationEnum::COMPOSITION
        );

        return $composition === null ? null : $this->noteSur20($composition, $eleveId);
    }

    /**
     * La note d'un élève à une évaluation, ramenée sur 20.
     *
     * Retourne null si la note n'a pas été saisie — l'évaluation est alors
     * ignorée, comme si elle n'existait pas. Une absence, elle, vaut 0 : c'est
     * une note, pas une absence de note.
     */
    private function noteSur20(Evaluation $evaluation, int $eleveId): ?float
    {
        $note = $evaluation->notes->firstWhere('eleve_id', $eleveId);

        if ($note === null) {
            return null;
        }

        if ($note->absent) {
            return 0.0;
        }

        if ($note->valeur === null) {
            return null;
        }

        $bareme = (int) ($evaluation->bareme ?: self::BAREME_REFERENCE);

        if ($bareme <= 0) {
            return null; // barème aberrant : on préfère ignorer que diviser par zéro
        }

        return ((float) $note->valeur) / $bareme * self::BAREME_REFERENCE;
    }

    /** La formule de l'établissement, figée. */
    private function combiner(?float $moyDevoirs, ?float $composition): ?float
    {
        return match (true) {
            $moyDevoirs !== null && $composition !== null => ($moyDevoirs + $composition) / 2,
            $moyDevoirs !== null                          => $moyDevoirs,
            $composition !== null                         => $composition,
            default                                       => null,
        };
    }

    // ------------------------------------------------------------------
    // Classement
    // ------------------------------------------------------------------

    /**
     * Classement « competition ranking » : deux ex æquo partagent le rang et
     * le suivant saute (1, 2, 2, 4). Les élèves sans moyenne ne sont pas classés.
     *
     * @param  array<int, float|null>  $moyennes  [eleve_id => moyenne affichée]
     * @return array<int, array{rang:int, ex_aequo:bool}>
     */
    private function classer(array $moyennes): array
    {
        $classables = array_filter($moyennes, fn (?float $m) => $m !== null);

        if ($classables === []) {
            return [];
        }

        arsort($classables);

        // Combien d'élèves partagent chaque moyenne : sert à marquer les ex æquo.
        $effectifs = [];
        foreach ($classables as $moyenne) {
            $cle              = (string) $moyenne;
            $effectifs[$cle]  = ($effectifs[$cle] ?? 0) + 1;
        }

        $resultat       = [];
        $position       = 0;
        $rangCourant    = 0;
        $moyennePrecede = null;

        foreach ($classables as $eleveId => $moyenne) {
            $position++;

            // Même moyenne que le précédent : même rang, sinon rang = position.
            if ($moyennePrecede === null || $moyenne !== $moyennePrecede) {
                $rangCourant    = $position;
                $moyennePrecede = $moyenne;
            }

            $resultat[$eleveId] = [
                'rang'     => $rangCourant,
                'ex_aequo' => $effectifs[(string) $moyenne] > 1,
            ];
        }

        return $resultat;
    }

    /**
     * Arrondi à la décimale demandée, en neutralisant l'imprécision binaire.
     *
     * round(10.125, 2) peut rendre 10,12 : 10.125 n'est pas représentable
     * exactement en flottant et sa valeur machine est légèrement inférieure.
     * Le epsilon relatif rétablit le demi-arrondi supérieur attendu.
     */
    private function arrondir(float $valeur, int $decimales): float
    {
        $epsilon = abs($valeur) * 1e-9;

        return round($valeur + ($valeur >= 0 ? $epsilon : -$epsilon), $decimales);
    }
}
