<?php

namespace App\Interfaces;

use App\Models\FactureMensualite;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FinanceTresorierRepositoryInterface
{
    /**
     * Inscriptions de l'annee en cours restant a encaisser (non annulees et
     * non soldees), les plus anciennes d'abord.
     */
    public function paginateAEncaisser(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Charge l'inscription en verrouillant la ligne pour la duree de la
     * transaction, afin que deux versements concurrents ne puissent pas
     * valider tous les deux la regle « montant <= reste a payer ».
     */
    public function findInscriptionForUpdate(int|string $id): Inscription;

    public function createPaiement(array $data): PaiementInscription;

    public function findPaiementById(int|string $id): PaiementInscription;

    public function paginatePaiements(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Numero sequentiel au format REC-001-26, remis a 001 a chaque annee scolaire.
     */
    public function nextNumeroRecu(int|string $anneeScolaireId): string;

    // ─── Mensualites ──────────────────────────────────────────────────────────

    /**
     * Inscriptions de l'annee en cours ayant au moins une mensualite non soldee,
     * echeancier complet charge. Vue tresorier regroupee par eleve.
     */
    public function paginateInscriptionsAvecMensualites(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Charge l'inscription en verrouillant ses mensualites, pour un versement
     * reparti sur plusieurs mois dans une meme transaction.
     */
    public function findInscriptionAvecMensualitesForUpdate(int|string $id): Inscription;

    /**
     * Mensualites de l'annee en cours restant a encaisser (non soldees), les
     * plus anciennes (mois) d'abord.
     */
    public function paginateMensualitesAEncaisser(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Charge la mensualite en verrouillant la ligne pour la duree de la
     * transaction, afin que deux versements concurrents ne depassent pas le
     * reste a payer.
     */
    public function findMensualiteForUpdate(int|string $id): Mensualite;

    public function createPaiementMensualite(array $data): PaiementMensualite;

    public function findPaiementMensualiteById(int|string $id): PaiementMensualite;

    public function paginatePaiementsMensualite(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Numero sequentiel au format REM-001-26 (mensualites), remis a 001 a
     * chaque annee scolaire.
     */
    public function nextNumeroRecuMensualite(int|string $anneeScolaireId): string;

    // ─── Factures de mensualites ──────────────────────────────────────────────

    public function createFactureMensualite(array $data): FactureMensualite;

    public function findFactureMensualiteById(int|string $id): FactureMensualite;

    public function paginateFacturesMensualite(int $perPage, string $search): LengthAwarePaginator;

    /**
     * Numero sequentiel au format FAM-001-26 (facture de mensualites), remis a
     * 001 a chaque annee scolaire.
     */
    public function nextNumeroFacture(int|string $anneeScolaireId): string;
}
