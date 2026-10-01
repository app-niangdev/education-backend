<?php

namespace App\Http\Requests\Concerns;

/**
 * Les regles du bloc `contrat` envoye a la creation d'un membre du personnel.
 *
 * Identiques pour un enseignant, un tresorier ou un surveillant : les conditions
 * d'engagement ne dependent pas du poste. La coherence entre les dates est
 * verifiee par le service, seul endroit ou elle vaut aussi pour un contrat
 * modifie ou renouvele.
 */
trait ValideLeContrat
{
    /**
     * @param string $prefixe Bloc portant les champs. Vide, les regles visent
     *                        la racine du corps (module contrat autonome).
     */
    protected function reglesContrat(string $prefixe = 'contrat'): array
    {
        $regles = [
            'type_contrat'          => ['nullable', 'in:permanent,vacataire,stagiaire'],
            'date_debut'            => ['nullable', 'date'],
            // Le service refuse une fin anterieure au debut et exige une date de
            // fin pour les contrats bornes : la regle est la meme partout.
            'date_fin'              => ['nullable', 'date'],
            'duree_periode_essai'   => ['nullable', 'integer', 'min:0', 'max:24'],
            'salaire_base'          => ['nullable', 'integer', 'min:0'],
            'mode_remuneration'     => ['nullable', 'in:MENSUEL,HORAIRE'],
            'fonction'              => ['nullable', 'string', 'max:255'],
            'lieu_travail'          => ['nullable', 'string', 'max:255'],
            // 168 h = une semaine entiere : au-dela, la saisie est une erreur.
            'volume_horaire_hebdo'  => ['nullable', 'integer', 'min:0', 'max:168'],
            'observations'          => ['nullable', 'string'],
        ];

        $prefixees = $this->prefixer($regles, $prefixe);

        // Le bloc lui-meme n'existe que s'il est imbrique.
        return $prefixe === ''
            ? $prefixees
            : [$prefixe => ['nullable', 'array'], ...$prefixees];
    }

    protected function messagesContrat(string $prefixe = 'contrat'): array
    {
        return $this->prefixer([
            'type_contrat.in'          => "Le type de contrat doit être : permanent, vacataire ou stagiaire.",
            'date_debut.date'          => "La date de début du contrat n'est pas valide.",
            'date_fin.date'            => "La date de fin du contrat n'est pas valide.",
            'duree_periode_essai.integer' => "La période d'essai doit être un nombre de mois.",
            'duree_periode_essai.max'  => "La période d'essai ne peut pas dépasser 24 mois.",
            'salaire_base.integer'     => "Le montant de la rémunération doit être un nombre entier.",
            'salaire_base.min'         => "Le montant de la rémunération doit être positif.",
            'mode_remuneration.in'     => "Le mode de rémunération doit être : mensuel ou horaire.",
            'volume_horaire_hebdo.max' => "Le volume horaire hebdomadaire ne peut pas dépasser 168 heures.",
        ], $prefixe);
    }

    private function prefixer(array $entrees, string $prefixe): array
    {
        if ($prefixe === '') {
            return $entrees;
        }

        $prefixees = [];

        foreach ($entrees as $cle => $valeur) {
            $prefixees["{$prefixe}.{$cle}"] = $valeur;
        }

        return $prefixees;
    }
}
