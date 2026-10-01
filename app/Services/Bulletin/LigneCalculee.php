<?php

namespace App\Services\Bulletin;

use App\Enums\AppreciationEnum;

/**
 * Le résultat du calcul d'une matière pour un élève, avant écriture en base.
 *
 * Un objet plutôt qu'un tableau associatif : ces huit valeurs circulent entre
 * quatre méthodes du calculateur, et une clé mal orthographiée dans un tableau
 * passerait inaperçue jusqu'à produire un bulletin faux.
 *
 * `rang` et `rang_ex_aequo` ne sont pas ici : ils dépendent de toute la classe
 * et sont attribués dans un second temps par CalculateurBulletin::rangsParMatiere().
 */
final class LigneCalculee
{
    public function __construct(
        public readonly ?int $matiereId,
        public readonly string $matiereNom,
        public readonly int $ordre,
        public readonly ?float $moyDevoirs,
        public readonly ?float $composition,
        public readonly ?float $moyenne,
        public readonly ?int $coefficient,
        public readonly ?float $moyXCoef,
        public readonly bool $notee,
    ) {}

    /** Une matière au programme mais sans aucune note : imprimée en tirets. */
    public static function nonNotee(?int $matiereId, string $matiereNom, int $ordre): self
    {
        return new self(
            matiereId:   $matiereId,
            matiereNom:  $matiereNom,
            ordre:       $ordre,
            moyDevoirs:  null,
            composition: null,
            moyenne:     null,
            coefficient: null,
            moyXCoef:    null,
            notee:       false,
        );
    }

    public function appreciation(): ?AppreciationEnum
    {
        return AppreciationEnum::pourMoyenne($this->moyenne);
    }

    /**
     * La moyenne telle qu'elle s'affiche, arrondie à deux décimales.
     * C'est cette valeur — et non la moyenne exacte — qui sert à départager
     * les rangs : deux élèves affichés à 12,50 doivent partager le même rang.
     */
    public function moyenneAffichee(): ?float
    {
        return $this->moyenne === null ? null : round($this->moyenne, 2);
    }
}
