<?php

namespace App\Interfaces;

use App\Models\AnneeScolaire;
use App\Models\User;

interface StatistiqueServiceInterface
{
    /**
     * Tableau de bord complet du manager pour une annee scolaire (celle en
     * cours par defaut) : effectifs, repartitions et indicateurs financiers.
     *
     * @return array{
     *     annee_scolaire: array,
     *     effectifs: array,
     *     eleves_par_sexe: array,
     *     eleves_par_niveau: array,
     *     inscriptions_par_statut: array,
     *     finances: array
     * }
     */
    public function dashboard(?AnneeScolaire $annee = null): array;

    /**
     * Tableau de bord financier du tresorier pour une annee scolaire (celle en
     * cours par defaut) : recouvrement, encaissements du jour/mois, repartition
     * par mode de paiement, mensualites par statut et restes a encaisser.
     *
     * @return array{
     *     annee_scolaire: array|null,
     *     finances: array,
     *     encaissements: array,
     *     par_mode_paiement: array,
     *     mensualites_par_statut: array,
     *     a_encaisser: array
     * }
     */
    public function dashboardTresorier(?AnneeScolaire $annee = null): array;

    /**
     * Tableau de bord de l'enseignant : ses affectations (classe × matière),
     * ses évaluations par type et période, et son taux de saisie des notes,
     * borné à l'année scolaire en cours.
     *
     * @return array{
     *     annee_scolaire: array|null,
     *     effectifs: array,
     *     evaluations_par_type: array,
     *     saisie_notes: array,
     *     mes_cours: array
     * }
     */
    public function dashboardEnseignant(User $enseignantUser, ?AnneeScolaire $annee = null): array;
}
