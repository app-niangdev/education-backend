<?php

namespace App\Services;

use App\Enums\StatutContratEnum;
use App\Enums\TypeContratEnum;
use App\Helpers\ColorHelper;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ContratRepositoryInterface;
use App\Interfaces\ContratServiceInterface;
use App\Models\Contrat;
use App\Interfaces\QrCodeServiceInterface;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Parametrage;
use App\Models\Surveillant;
use App\Models\Tresorier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContratService implements ContratServiceInterface
{
    /**
     * Les profils contractables, indexes par le mot-cle attendu dans l'URL.
     * Cette table evite d'accepter n'importe quelle classe depuis la requete.
     */
    private const CONTRACTABLES = [
        'enseignant'  => Enseignant::class,
        'tresorier'   => Tresorier::class,
        'surveillant' => Surveillant::class,
    ];

    /** La fonction par defaut inscrite au contrat, selon le profil. */
    private const FONCTIONS = [
        Enseignant::class  => 'Enseignant',
        Tresorier::class   => 'Trésorier',
        Surveillant::class => 'Surveillant',
    ];

    public function __construct(
        private readonly ContratRepositoryInterface  $repository,
        private readonly ActivityLogServiceInterface $activityLog,
        private readonly QrCodeServiceInterface      $qrCode,
    ) {}

    public function list(int $perPage, string $search, array $filtres = []): LengthAwarePaginator
    {
        // Un contrat echu doit apparaitre expire meme si personne n'a ouvert la
        // fiche depuis : on rattrape le retard avant de lire la liste.
        $this->repository->expirerEchus();

        return $this->repository->paginate($perPage, $search, $filtres);
    }

    public function find(int|string $id): Contrat
    {
        return $this->repository->findById($id);
    }

    public function historique(string $type, int|string $id): Collection
    {
        $classe = self::CONTRACTABLES[$type] ?? null;

        if (!$classe) {
            abort(404, 'Type de personnel inconnu.');
        }

        return $this->repository->forContractable($classe::findOrFail($id));
    }

    /**
     * Le contrat initial, cree dans la foulee de l'enregistrement du profil.
     *
     * Les valeurs manquantes prennent des defauts raisonnables : un contrat
     * incomplet reste preferable a un employe sans contrat, qui n'apparaitrait
     * plus nulle part dans le suivi.
     */
    public function creerPourNouveauProfil(Model $contractable, array $donnees, User $authUser): Contrat
    {
        $donnees['contractable_type'] = $contractable->getMorphClass();
        $donnees['contractable_id']   = $contractable->getKey();

        $donnees['date_debut'] ??= now()->toDateString();
        $donnees['fonction']   ??= self::FONCTIONS[$contractable::class] ?? null;
        $donnees['statut']     ??= StatutContratEnum::ACTIF->value;

        return $this->enregistrer($donnees, $authUser, 'created');
    }

    public function create(array $data, User $authUser): Contrat
    {
        $type   = $data['contractable_type'] ?? null;
        $classe = self::CONTRACTABLES[$type] ?? null;

        if (!$classe) {
            abort(422, 'Type de personnel inconnu.');
        }

        $contractable = $classe::findOrFail($data['contractable_id']);

        // Deux contrats en cours pour une meme personne rendraient indecidable
        // le salaire et le terme qui font foi.
        $this->refuserSiDejaEngage($contractable);

        $data['contractable_type'] = $contractable->getMorphClass();
        $data['contractable_id']   = $contractable->getKey();
        $data['fonction']        ??= self::FONCTIONS[$classe] ?? null;

        return DB::transaction(fn () => $this->enregistrer($data, $authUser, 'created'));
    }

    public function update(int|string $id, array $data, User $authUser): Contrat
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $contrat   = $this->repository->findById($id);
            $oldValues = $contrat->toArray();

            // Un contrat clos fige les conditions passees : les rouvrir
            // reecrirait l'historique de la relation de travail.
            if ($contrat->statut?->estClos()) {
                throw ValidationException::withMessages([
                    'statut' => "Un contrat {$contrat->statut->libelle()} ne peut plus être modifié. Créez un renouvellement.",
                ]);
            }

            $this->validerCoherence(
                type:   $this->typeContrat($data['type_contrat'] ?? $contrat->type_contrat),
                debut:  $this->date($data['date_debut'] ?? $contrat->date_debut),
                fin:    $this->date($data['date_fin']    ?? $contrat->date_fin),
            );

            $contrat = $this->repository->update($contrat, $data);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'contrats',
                description: "Modification du contrat {$contrat->numero_contrat} — {$this->nomEmploye($contrat)}",
                subject:     $contrat,
                oldValues:   $oldValues,
                newValues:   $contrat->toArray(),
            );

            return $contrat;
        });
    }

    public function resilier(int|string $id, array $data, User $authUser): Contrat
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $contrat   = $this->repository->findById($id);
            $oldValues = $contrat->toArray();

            if ($contrat->statut?->estClos()) {
                throw ValidationException::withMessages([
                    'statut' => "Ce contrat est déjà {$contrat->statut->libelle()}.",
                ]);
            }

            $dateResiliation = $this->date($data['date_resiliation'] ?? null) ?? now();

            // Resilier avant le debut ne decrit aucune situation reelle : le
            // contrat n'a pas encore produit d'effet, il serait a supprimer.
            if ($contrat->date_debut && $dateResiliation->lessThan($contrat->date_debut)) {
                throw ValidationException::withMessages([
                    'date_resiliation' => "La date de résiliation ne peut pas précéder le début du contrat ({$contrat->date_debut->format('d/m/Y')}).",
                ]);
            }

            $contrat = $this->repository->update($contrat, [
                'statut'            => StatutContratEnum::RESILIE->value,
                'date_resiliation'  => $dateResiliation->toDateString(),
                'motif_resiliation' => $data['motif_resiliation'] ?? null,
            ]);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'contrats',
                description: "Résiliation du contrat {$contrat->numero_contrat} — {$this->nomEmploye($contrat)}",
                subject:     $contrat,
                oldValues:   $oldValues,
                newValues:   $contrat->toArray(),
            );

            return $contrat;
        });
    }

    /**
     * Ouvre le contrat suivant et clot le precedent.
     *
     * Le nouveau reprend les conditions de l'ancien, que la requete peut
     * surcharger : un renouvellement change le plus souvent les seules dates.
     */
    public function renouveler(int|string $id, array $data, User $authUser): Contrat
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $precedent = $this->repository->findById($id);

            $debut = $this->date($data['date_debut'] ?? null)
                ?? ($precedent->date_fin?->copy()->addDay() ?? now());

            $nouveau = $this->enregistrer(array_merge([
                'contractable_type'    => $precedent->contractable_type,
                'contractable_id'      => $precedent->contractable_id,
                'type_contrat'         => $precedent->type_contrat?->value,
                'salaire_base'         => $precedent->salaire_base,
                'mode_remuneration'    => $precedent->mode_remuneration?->value,
                'fonction'             => $precedent->fonction,
                'lieu_travail'         => $precedent->lieu_travail,
                'volume_horaire_hebdo' => $precedent->volume_horaire_hebdo,
            ], $data, [
                'date_debut'        => $debut->toDateString(),
                'contrat_parent_id' => $precedent->id,
                'statut'            => StatutContratEnum::ACTIF->value,
                // La periode d'essai ne se represente pas : elle appartient au
                // premier engagement, pas a sa prolongation.
                'duree_periode_essai' => null,
            ]), $authUser, 'created', "Renouvellement du contrat {$precedent->numero_contrat}");

            // L'ancien contrat cede la place : le laisser actif ferait deux
            // engagements simultanes pour la meme personne.
            if (!$precedent->statut?->estClos()) {
                $this->repository->update($precedent, [
                    'statut'           => StatutContratEnum::EXPIRE->value,
                    'date_fin'         => $precedent->date_fin?->toDateString()
                        ?? $debut->copy()->subDay()->toDateString(),
                ]);
            }

            return $nouveau;
        });
    }

    public function delete(int|string $id, User $authUser): void
    {
        $contrat = $this->repository->findById($id);

        $this->repository->delete($contrat);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'contrats',
            description: "Suppression du contrat {$contrat->numero_contrat} — {$this->nomEmploye($contrat)}",
            subject:     $contrat,
            oldValues:   $contrat->toArray(),
        );
    }

    /**
     * Rassemble le contrat, son employe, l'etablissement et le QR code de
     * verification, tels que le template du contrat imprime les attend.
     */
    public function dataForPdf(int|string $id): array
    {
        $contrat       = $this->repository->findById($id);
        $lien          = $contrat->lienVerification();
        $etablissement = Etablissement::first();

        return [
            'contrat'       => $contrat,
            'employe'       => $contrat->contractable?->user,
            'matricule'     => $contrat->contractable?->matricule,
            'etablissement' => $etablissement,
            'logo'          => $etablissement?->logoDataUri(),
            'lien'          => $lien,
            // Le QR peut manquer (generation en echec) : le template retombe
            // alors sur l'URL en clair, qui reste saisissable a la main.
            'qrCode'        => $lien ? $this->qrCode->dataUri($lien) : null,
        ] + ColorHelper::palette(Parametrage::couleurPrincipale());
    }

    /**
     * Ce que la page publique de verification est en droit de montrer.
     *
     * Elle repond a une seule question : ce papier correspond-il a un contrat
     * enregistre, et lie-t-il encore son porteur a l'etablissement ? Le salaire
     * et les motifs de resiliation n'y ont pas leur place — n'importe qui
     * detenant le code y aurait acces.
     */
    public function verifierParCode(string $code): ?array
    {
        $contrat = $this->repository->findByCodeVerification($code);

        if (!$contrat) {
            return null;
        }

        $user = $contrat->contractable?->user;

        return [
            'numero_contrat'   => $contrat->numero_contrat,
            'employe'          => trim("{$user?->first_name} {$user?->last_name}") ?: null,
            'matricule'        => $contrat->contractable?->matricule,
            'fonction'         => $contrat->fonction,
            'type_contrat'     => $contrat->type_contrat?->libelle(),
            'statut'           => $contrat->statut?->value,
            'statut_libelle'   => $contrat->statut?->libelle(),
            'date_debut'       => $contrat->date_debut?->toDateString(),
            'date_fin'         => $contrat->date_fin?->toDateString(),
            'lieu_travail'     => $contrat->lieu_travail,
            // « En cours » ne se lit pas dans le seul statut : un contrat actif
            // dont le terme est passe n'engage plus, meme si personne n'a
            // encore ouvert la liste qui le bascule en EXPIRE.
            'est_en_vigueur'   => $contrat->statut?->estEnCours()
                && !($contrat->date_fin && $contrat->date_fin->isPast()),
            'etablissement'    => Etablissement::first()?->nom,
            'verifie_le'       => now()->toIso8601String(),
        ];
    }

    /**
     * Numerote, valide puis enregistre un contrat, et journalise l'operation.
     * Partage par la creation initiale, la creation manuelle et le renouvellement.
     */
    private function enregistrer(array $data, User $authUser, string $action, ?string $prefixeLog = null): Contrat
    {
        $debut = $this->date($data['date_debut'] ?? null) ?? now();

        $this->validerCoherence(
            type:  $this->typeContrat($data['type_contrat'] ?? null),
            debut: $debut,
            fin:   $this->date($data['date_fin'] ?? null),
        );

        if (empty($data['numero_contrat'])) {
            $data['numero_contrat'] = $this->repository->nextNumero($debut->year);
        }

        $contrat = $this->repository->create($data);

        $description = $prefixeLog
            ?? "Création du contrat {$contrat->numero_contrat}";

        $this->activityLog->log(
            user:        $authUser,
            action:      $action,
            module:      'contrats',
            description: "{$description} — {$this->nomEmploye($contrat)}",
            subject:     $contrat,
            newValues:   $contrat->toArray(),
        );

        return $contrat;
    }

    /**
     * Les regles qu'aucun contrat ne peut enfreindre, quelle que soit la porte
     * d'entree : un terme avant le debut, ou un contrat borne sans terme.
     */
    private function validerCoherence(?TypeContratEnum $type, ?Carbon $debut, ?Carbon $fin): void
    {
        if ($fin && $debut && $fin->lessThanOrEqualTo($debut)) {
            throw ValidationException::withMessages([
                'date_fin' => "La date de fin doit être postérieure à la date de début.",
            ]);
        }

        if ($type?->exigeDateFin() && !$fin) {
            throw ValidationException::withMessages([
                'date_fin' => "Un contrat {$type->libelle()} doit avoir une date de fin.",
            ]);
        }
    }

    /** Un employe ne peut avoir qu'un seul contrat en cours a la fois. */
    private function refuserSiDejaEngage(Model $contractable): void
    {
        $actif = $contractable->contratActif()->first();

        if ($actif) {
            throw ValidationException::withMessages([
                'contractable_id' => "Cette personne a déjà un contrat en cours ({$actif->numero_contrat}). Résiliez-le ou renouvelez-le.",
            ]);
        }
    }

    private function typeContrat(mixed $valeur): ?TypeContratEnum
    {
        return $valeur instanceof TypeContratEnum
            ? $valeur
            : ($valeur ? TypeContratEnum::tryFrom((string) $valeur) : null);
    }

    private function date(mixed $valeur): ?Carbon
    {
        return $valeur ? Carbon::parse($valeur) : null;
    }

    private function nomEmploye(Contrat $contrat): string
    {
        $user = $contrat->contractable?->user;

        return trim("{$user?->first_name} {$user?->last_name}") ?: 'personnel inconnu';
    }
}
