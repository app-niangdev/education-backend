<?php

namespace App\Repositories;

use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use App\Interfaces\FinanceTresorierRepositoryInterface;
use App\Models\AnneeScolaire;
use App\Models\FactureMensualite;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinanceTresorierRepository implements FinanceTresorierRepositoryInterface
{
    // Nom complet d'un eleve dans les deux ordres : une saisie « Awa Diop »
    // comme « Diop Awa » doit trouver le meme eleve, sur les six recherches
    // de ce repository qui filtrent sur son nom/prenom.
    private const SQL_PRENOM_NOM = "(prenom || ' ' || nom) ilike ?";
    private const SQL_NOM_PRENOM = "(nom || ' ' || prenom) ilike ?";

    /**
     * Inscriptions restant a encaisser sur l'annee en cours : celles qui ne
     * sont pas annulees et pas encore soldees. Cela couvre les EN_ATTENTE (a
     * valider par un premier versement) et les VALIDEE partiellement payees
     * (a completer). Les plus anciennes d'abord : ce sont les plus urgentes.
     */
    public function paginateAEncaisser(int $perPage, string $search): LengthAwarePaginator
    {
        return Inscription::query()
            ->with(['eleve', 'classe.niveau', 'anneeScolaire', 'paiements'])
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->where('statut_paiement', '!=', StatutPaiementEnum::PAYE)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_inscription', 'ilike', "%{$search}%")
                  ->orWhereHas('eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      // Nom complet dans les deux ordres.
                      ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                      ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]))
                  ->orWhereHas('classe', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->orderBy('date_inscription', 'asc')
            ->orderBy('numero_inscription', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findInscriptionForUpdate(int|string $id): Inscription
    {
        // lockForUpdate() ne peut pas cohabiter avec un eager load : on
        // verrouille d'abord, on charge les relations ensuite.
        $inscription = Inscription::query()->lockForUpdate()->findOrFail($id);

        return $inscription->load(['eleve', 'classe.niveau', 'anneeScolaire', 'paiements']);
    }

    public function createPaiement(array $data): PaiementInscription
    {
        return PaiementInscription::create($data);
    }

    public function findPaiementById(int|string $id): PaiementInscription
    {
        return PaiementInscription::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'inscription.anneeScolaire',
            'inscription.paiements',
            'utilisateur',
        ])->findOrFail($id);
    }

    public function paginatePaiements(int $perPage, string $search): LengthAwarePaginator
    {
        return PaiementInscription::query()
            ->with(['inscription.eleve', 'utilisateur'])
            ->whereHas('inscription.anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_recu', 'ilike', "%{$search}%")
                  ->orWhere('numero_transaction', 'ilike', "%{$search}%")
                  ->orWhereHas('inscription', fn ($q) => $q->where('numero_inscription', 'ilike', "%{$search}%"))
                  ->orWhereHas('inscription.eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                      ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Format REC-001-26, aligne sur celui des inscriptions. Le compteur est
     * scope a l'annee scolaire et inclut les recus supprimes pour ne jamais
     * reattribuer un numero deja emis.
     */
    public function nextNumeroRecu(int|string $anneeScolaireId): string
    {
        $annee  = AnneeScolaire::findOrFail($anneeScolaireId);
        $suffix = $annee->date_debut->format('y');

        $dernier = PaiementInscription::withTrashed()
            ->whereHas('inscription', fn ($q) => $q->withTrashed()->where('annee_scolaire_id', $annee->id))
            ->where('numero_recu', 'like', "REC-%-{$suffix}")
            ->orderByRaw("CAST(SPLIT_PART(numero_recu, '-', 2) AS INTEGER) DESC")
            ->value('numero_recu');

        $next = $dernier ? ((int) explode('-', $dernier)[1]) + 1 : 1;

        return sprintf('REC-%03d-%s', $next, $suffix);
    }

    // ─── Mensualites ──────────────────────────────────────────────────────────

    /**
     * Inscriptions de l'annee en cours ayant au moins une mensualite non soldee,
     * avec l'echeancier complet (mensualites + paiements) charge. Regroupe la
     * vue tresorier par eleve plutot que par mois isole.
     */
    public function paginateInscriptionsAvecMensualites(int $perPage, string $search): LengthAwarePaginator
    {
        return Inscription::query()
            ->with([
                'eleve',
                'classe.niveau',
                'anneeScolaire',
                'mensualites' => fn ($q) => $q->orderBy('annee')->orderBy('mois'),
                'mensualites.paiements',
            ])
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->where('statut_inscription', '!=', \App\Enums\StatutInscriptionEnum::ANNULEE)
            ->whereHas('mensualites', fn ($q) => $q->nonSolde())
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_inscription', 'ilike', "%{$search}%")
                  ->orWhereHas('eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                      ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]))
                  ->orWhereHas('classe', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->orderBy('numero_inscription', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Charge l'inscription et verrouille ses mensualites non soldees pour un
     * versement reparti (imputation sur plusieurs mois en une transaction).
     */
    public function findInscriptionAvecMensualitesForUpdate(int|string $id): Inscription
    {
        // Verrou sur les mensualites concernees, puis chargement des relations.
        Mensualite::where('inscription_id', $id)->lockForUpdate()->get();

        return Inscription::with([
            'anneeScolaire',
            'mensualites' => fn ($q) => $q->orderBy('annee')->orderBy('mois'),
            'mensualites.paiements',
        ])->findOrFail($id);
    }

    public function paginateMensualitesAEncaisser(int $perPage, string $search): LengthAwarePaginator
    {
        return Mensualite::query()
            ->with(['inscription.eleve', 'inscription.classe.niveau', 'inscription.anneeScolaire', 'paiements'])
            ->whereHas('inscription.anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->nonSolde()
            ->when($search, fn ($q) => $q->whereHas('inscription', fn ($q) => $q
                ->where('numero_inscription', 'ilike', "%{$search}%")
                ->orWhereHas('eleve', fn ($q) => $q
                    ->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenom', 'ilike', "%{$search}%")
                    ->orWhere('matricule', 'ilike', "%{$search}%")
                    ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                    ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]))))
            ->orderBy('annee', 'asc')
            ->orderBy('mois', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findMensualiteForUpdate(int|string $id): Mensualite
    {
        $mensualite = Mensualite::query()->lockForUpdate()->findOrFail($id);

        return $mensualite->load(['inscription.eleve', 'inscription.anneeScolaire', 'paiements']);
    }

    public function createPaiementMensualite(array $data): PaiementMensualite
    {
        return PaiementMensualite::create($data);
    }

    public function findPaiementMensualiteById(int|string $id): PaiementMensualite
    {
        return PaiementMensualite::with([
            'mensualite.inscription.eleve',
            'mensualite.inscription.classe.niveau',
            'mensualite.inscription.anneeScolaire',
            'mensualite.paiements',
            'utilisateur',
        ])->findOrFail($id);
    }

    public function paginatePaiementsMensualite(int $perPage, string $search): LengthAwarePaginator
    {
        return PaiementMensualite::query()
            ->with(['mensualite.inscription.eleve', 'utilisateur'])
            ->whereHas('mensualite.inscription.anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_recu', 'ilike', "%{$search}%")
                  ->orWhere('numero_transaction', 'ilike', "%{$search}%")
                  ->orWhereHas('mensualite.inscription', fn ($q) => $q->where('numero_inscription', 'ilike', "%{$search}%"))
                  ->orWhereHas('mensualite.inscription.eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                      ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Format REM-001-26, aligne sur celui des inscriptions mais prefixe REM
     * (mensualite). Compteur scope a l'annee scolaire, recus supprimes inclus
     * pour ne jamais reattribuer un numero deja emis.
     */
    public function nextNumeroRecuMensualite(int|string $anneeScolaireId): string
    {
        $annee  = AnneeScolaire::findOrFail($anneeScolaireId);
        $suffix = $annee->date_debut->format('y');

        $dernier = PaiementMensualite::withTrashed()
            ->whereHas('mensualite.inscription', fn ($q) => $q->withTrashed()->where('annee_scolaire_id', $annee->id))
            ->where('numero_recu', 'like', "REM-%-{$suffix}")
            ->orderByRaw("CAST(SPLIT_PART(numero_recu, '-', 2) AS INTEGER) DESC")
            ->value('numero_recu');

        $next = $dernier ? ((int) explode('-', $dernier)[1]) + 1 : 1;

        return sprintf('REM-%03d-%s', $next, $suffix);
    }

    // ─── Factures de mensualites (plusieurs mois, un seul document) ────────────

    public function createFactureMensualite(array $data): FactureMensualite
    {
        return FactureMensualite::create($data);
    }

    public function findFactureMensualiteById(int|string $id): FactureMensualite
    {
        return FactureMensualite::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'inscription.anneeScolaire',
            'inscription.mensualites.paiements',
            'lignes.mensualite.paiements',
            'utilisateur',
        ])->findOrFail($id);
    }

    public function paginateFacturesMensualite(int $perPage, string $search): LengthAwarePaginator
    {
        return FactureMensualite::query()
            ->with(['inscription.eleve', 'utilisateur', 'lignes.mensualite'])
            ->whereHas('inscription.anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_facture', 'ilike', "%{$search}%")
                  ->orWhere('numero_transaction', 'ilike', "%{$search}%")
                  ->orWhereHas('inscription', fn ($q) => $q->where('numero_inscription', 'ilike', "%{$search}%"))
                  ->orWhereHas('inscription.eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      ->orWhereRaw(self::SQL_PRENOM_NOM, ["%{$search}%"])
                      ->orWhereRaw(self::SQL_NOM_PRENOM, ["%{$search}%"]));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Format FAM-001-26 (facture de mensualites). Compteur scope a l'annee
     * scolaire, factures supprimees incluses pour ne jamais reattribuer un
     * numero deja emis.
     */
    public function nextNumeroFacture(int|string $anneeScolaireId): string
    {
        $annee  = AnneeScolaire::findOrFail($anneeScolaireId);
        $suffix = $annee->date_debut->format('y');

        $dernier = FactureMensualite::withTrashed()
            ->whereHas('inscription', fn ($q) => $q->withTrashed()->where('annee_scolaire_id', $annee->id))
            ->where('numero_facture', 'like', "FAM-%-{$suffix}")
            ->orderByRaw("CAST(SPLIT_PART(numero_facture, '-', 2) AS INTEGER) DESC")
            ->value('numero_facture');

        $next = $dernier ? ((int) explode('-', $dernier)[1]) + 1 : 1;

        return sprintf('FAM-%03d-%s', $next, $suffix);
    }
}
