<?php

namespace App\Interfaces;

use App\Models\FactureMensualite;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FinanceTresorierServiceInterface
{
    /**
     * Liste paginee des inscriptions restant a encaisser (a valider ou a
     * completer) pour l'annee scolaire en cours.
     */
    public function listAEncaisser(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Enregistre un versement sur une inscription et la valide.
     *
     * L'inscription passe a VALIDEE des lors qu'un montant > 0 est encaisse.
     * statut_paiement devient PARTIEL, ou PAYE si la totalite est versee.
     */
    public function validerInscription(int|string $inscriptionId, array $data, User $authUser): Inscription;

    public function listPaiements(int $perPage, string $search): LengthAwarePaginator;

    public function findPaiement(int|string $id): PaiementInscription;

    /**
     * Donnees du justificatif d'un paiement d'inscription. Le document est un
     * recu si le versement solde l'inscription, une decharge sinon.
     *
     * @return array<string, mixed>
     */
    public function dataForRecuInscription(int|string $paiementId): array;

    // ─── Mensualites ──────────────────────────────────────────────────────────

    /**
     * Liste paginee, regroupee par eleve (inscription), des mensualites de
     * l'annee en cours. Chaque inscription porte son echeancier complet.
     */
    public function listInscriptionsMensualites(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Versement global sur une inscription, impute automatiquement sur ses
     * mensualites non soldees, des plus anciennes aux plus recentes.
     */
    public function payerMensualitesReparti(int|string $inscriptionId, array $data, User $authUser): Inscription;

    /**
     * Encaisse plusieurs mois en une seule facture. En mode AUTOMATIQUE le
     * montant recu est impute en cascade sur les mois non soldes du plus ancien
     * au plus recent (le surplus devient une avance sur le mois suivant) ; en
     * mode SELECTION le tresorier choisit les mois et les montants imputes.
     */
    public function payerFactureMensualites(int|string $inscriptionId, array $data, User $authUser): FactureMensualite;

    /** Liste paginee des factures de mensualites de l'annee en cours. */
    public function listFacturesMensualite(int $perPage, string $search): LengthAwarePaginator;

    public function findFactureMensualite(int|string $id): FactureMensualite;

    /**
     * Donnees du justificatif unique d'une facture multi-mois : le detail des
     * mois regles et la situation annuelle de l'eleve.
     *
     * @return array<string, mixed>
     */
    public function dataForFactureMensualite(int|string $factureId): array;

    /**
     * Liste paginee des mensualites restant a encaisser (non soldees) pour
     * l'annee scolaire en cours.
     */
    public function listMensualitesAEncaisser(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Enregistre un versement sur une mensualite. Le reglement peut se faire en
     * plusieurs tranches : chaque versement > 0 et <= reste a payer fait passer
     * la mensualite a PARTIEL, ou PAYE lorsque la totalite est reglee.
     */
    public function payerMensualite(int|string $mensualiteId, array $data, User $authUser): Mensualite;

    public function listPaiementsMensualite(int $perPage, string $search): LengthAwarePaginator;

    public function findPaiementMensualite(int|string $id): PaiementMensualite;

    /**
     * Donnees du justificatif d'un paiement de mensualite. Le document est un
     * recu si le versement solde le mois concerne, une decharge sinon.
     *
     * @return array<string, mixed>
     */
    public function dataForRecuMensualite(int|string $paiementId): array;
}
