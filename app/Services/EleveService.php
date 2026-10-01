<?php

namespace App\Services;

use App\Enums\LienParenteEnum;
use App\Enums\StatutInscriptionEleveEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\EleveRepositoryInterface;
use App\Interfaces\EleveServiceInterface;
use App\Interfaces\TuteurRepositoryInterface;
use App\Models\Eleve;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EleveService implements EleveServiceInterface
{
    /**
     * Les colonnes NOT NULL de la table « tuteurs ». Une mise a jour ne peut
     * jamais les ramener a vide — voir champsSoumis().
     */
    private const COLONNES_TUTEUR_REQUISES = [
        'nom',
        'prenom',
        'telephone_principal',
        'adresse',
    ];

    public function __construct(
        private readonly EleveRepositoryInterface    $repository,
        private readonly TuteurRepositoryInterface   $tuteurRepository,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function list(int $perPage, string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search, $filters);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Eleve
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Eleve
    {
        return DB::transaction(function () use ($data, $authUser) {
            $eleveData = $this->normaliser($data['eleve'] ?? $data);

            if (empty($eleveData['matricule'])) {
                $eleveData['matricule'] = $this->repository->nextMatricule();
            }

            // Le bloc eleve est passe au resolveur : quand le tuteur est le
            // pere ou la mere, c'est de la que viennent ses coordonnees.
            $eleveData['tuteur_id'] = $this->resoudreTuteur(
                $data['tuteur'] ?? null,
                null,
                $eleveData,
            );

            // Premiere inscription dans l'etablissement : la date fait foi
            // cote serveur et n'est jamais acceptee depuis la requete.
            $eleve = $this->repository->create($eleveData);
            $eleve->forceFill(['date_inscription' => now()->toDateString()])->save();

            $eleve = $this->repository->findById($eleve->id);

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'eleves',
                description: "Création de l'élève « {$eleve->nom_complet} » (matricule : {$eleve->matricule})",
                subject:     $eleve,
                newValues:   $eleve->toArray(),
            );

            return $eleve;
        });
    }

    public function update(int|string $id, array $data, User $authUser): Eleve
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $eleve     = $this->repository->findById($id);
            $oldValues = $eleve->toArray();

            $eleveData = $this->normaliser($data['eleve'] ?? $data);

            // date_inscription et classe_actuelle_id sont pilotees par le
            // backend : on ecarte toute valeur venue du client.
            unset($eleveData['date_inscription'], $eleveData['classe_actuelle_id']);

            if (array_key_exists('tuteur', $data)) {
                // Les coordonnees du parent peuvent n'etre corrigees que
                // partiellement : on complete avec la fiche eleve deja en
                // base, sans quoi une modification du seul telephone effacerait
                // le nom recopie a la creation.
                $eleveData['tuteur_id'] = $this->resoudreTuteur(
                    $data['tuteur'],
                    $eleve->tuteur_id,
                    // Les valeurs envoyees priment sur celles deja en base.
                    $eleveData + $eleve->getAttributes(),
                );
            }

            $eleve = $this->repository->update($eleve, $eleveData);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'eleves',
                description: "Modification de l'élève « {$eleve->nom_complet} » (matricule : {$eleve->matricule})",
                subject:     $eleve,
                oldValues:   $oldValues,
                newValues:   $eleve->toArray(),
            );

            return $eleve;
        });
    }

    public function delete(int|string $id, User $authUser): void
    {
        $eleve = $this->repository->findById($id);

        if ($this->repository->isUsed($eleve)) {
            abort(422, "Impossible de supprimer cet élève : des inscriptions y sont rattachées.");
        }

        $this->repository->delete($eleve);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'eleves',
            description: "Suppression de l'élève « {$eleve->nom_complet} » (matricule : {$eleve->matricule})",
            subject:     $eleve,
            oldValues:   $eleve->toArray(),
        );
    }

    public function restore(int|string $id, User $authUser): Eleve
    {
        $eleve = $this->repository->findTrashedById($id);

        $this->repository->restore($eleve);

        $eleve = $this->repository->findById($eleve->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'restored',
            module:      'eleves',
            description: "Restauration de l'élève « {$eleve->nom_complet} » (matricule : {$eleve->matricule})",
            subject:     $eleve,
        );

        return $eleve;
    }

    public function synchroniserClasseActuelle(int|string $eleveId, ?int $classeId): void
    {
        Eleve::whereKey($eleveId)->update(['classe_actuelle_id' => $classeId]);
    }

    /**
     * Reprise de donnees : creation en masse des eleves deja scolarises.
     *
     * Chaque ligne est traitee dans sa propre transaction, et non le lot
     * entier dans une seule. Une reprise porte sur des registres tenus a la
     * main, ou une anomalie isolee est la norme : tout annuler pour une ligne
     * obligerait a rejouer un fichier de plusieurs centaines d'entrees pour
     * une seule correction. Ce qui passe est acquis, ce qui bloque est
     * rapporte avec son numero de ligne — l'agent corrige et reimporte le
     * reliquat.
     *
     * Le matricule fait foi pour reconnaitre un eleve deja repris : la ligne
     * est alors ignoree, jamais fusionnee. Un import ne doit pas ecraser une
     * fiche que l'ecole a pu corriger depuis.
     */
    public function importer(array $lignes, User $authUser): array
    {
        $details = ['crees' => [], 'ignores' => [], 'echecs' => []];

        // Les matricules deja emis, charges en une fois : interroger la base
        // pour chacune des lignes ferait autant de requetes que d'eleves.
        // Les eleves supprimes comptent — leur matricule reste reserve.
        $matriculesConnus = $this->repository->matriculesExistants(
            array_values(array_filter(array_map(
                fn (array $ligne) => $ligne['eleve']['matricule'] ?? null,
                $lignes,
            ))),
        );

        foreach ($lignes as $index => $ligne) {
            // Le numero du tableur, pour que le rapport renvoie a ce que
            // l'agent a sous les yeux. A defaut : index 0 = ligne 2, la
            // premiere etant l'en-tete.
            $numero    = $ligne['ligne'] ?? $index + 2;
            $matricule = $ligne['eleve']['matricule'] ?? null;
            $nomComplet = trim(
                ($ligne['eleve']['prenom'] ?? '') . ' ' . ($ligne['eleve']['nom'] ?? '')
            );

            if ($matricule !== null && isset($matriculesConnus[$matricule])) {
                $details['ignores'][] = [
                    'ligne'     => $numero,
                    'matricule' => $matricule,
                    'nom'       => $nomComplet,
                    'motif'     => 'Un élève porte déjà ce matricule : la ligne a été ignorée.',
                ];

                continue;
            }

            try {
                $eleve = DB::transaction(fn () => $this->creerDepuisImport($ligne));
            } catch (\Throwable $e) {
                report($e);

                $details['echecs'][] = [
                    'ligne'     => $numero,
                    'matricule' => $matricule,
                    'nom'       => $nomComplet,
                    'motif'     => $this->motifEchec($e),
                ];

                continue;
            }

            // Le matricule vient d'etre pris — y compris celui que le
            // repository a genere. Deux lignes du meme fichier portant le
            // meme matricule : la seconde est ignoree comme un doublon.
            $matriculesConnus[$eleve->matricule] = true;

            $details['crees'][] = [
                'ligne'     => $numero,
                'id'        => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom'       => $eleve->nom_complet,
            ];
        }

        // Une seule entree au journal pour tout le lot : une par eleve
        // noierait l'historique sous plusieurs centaines de lignes
        // identiques. Le detail des fiches creees reste dans le rapport.
        $this->activityLog->log(
            user:        $authUser,
            action:      'imported',
            module:      'eleves',
            description: sprintf(
                'Import de %d élève(s) : %d créé(s), %d ignoré(s), %d en échec',
                count($lignes),
                count($details['crees']),
                count($details['ignores']),
                count($details['echecs']),
            ),
            newValues:   $details,
        );

        return [
            'crees'   => count($details['crees']),
            'ignores' => count($details['ignores']),
            'echecs'  => count($details['echecs']),
            'details' => $details,
        ];
    }

    /**
     * Creation d'une fiche a partir d'une ligne du fichier.
     *
     * Reprend le chemin de create() — meme normalisation, meme resolution du
     * tuteur, donc meme mutualisation de la fratrie par le NIN — sans le
     * journal d'activite, tenu une fois pour le lot entier.
     */
    private function creerDepuisImport(array $ligne): Eleve
    {
        $eleveData = $this->normaliser($ligne['eleve']);

        if (empty($eleveData['matricule'])) {
            $eleveData['matricule'] = $this->repository->nextMatricule();
        }

        $eleveData['tuteur_id'] = $this->resoudreTuteur(
            $ligne['tuteur'] ?? null,
            null,
            $eleveData,
        );

        $eleve = $this->repository->create($eleveData);

        // Reprise de donnees : la date d'entree dans l'etablissement est
        // celle du registre d'origine quand le fichier la porte. A defaut,
        // le jour de l'import fait foi, comme pour une creation au guichet.
        $eleve->forceFill([
            'date_inscription' => $ligne['eleve']['date_inscription'] ?? now()->toDateString(),
        ])->save();

        return $this->repository->findById($eleve->id);
    }

    /**
     * Traduit l'exception en motif lisible dans le rapport.
     *
     * Le message brut d'une erreur SQL exposerait la structure de la base a
     * l'ecran ; on n'en retient que ce que l'agent peut corriger lui-meme.
     */
    private function motifEchec(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException) {
            return str_contains($e->getMessage(), 'unique')
                ? 'Un doublon empêche la création (matricule ou NIN déjà utilisé).'
                : "Les données de cette ligne ont été refusées par la base.";
        }

        return "La ligne n'a pas pu être enregistrée.";
    }

    /**
     * Applique les regles metier qui ne dependent pas d'un autre agregat.
     */
    private function normaliser(array $data): array
    {
        if (blank($data['nationalite'] ?? null)) {
            $data['nationalite'] = Eleve::NATIONALITE_PAR_DEFAUT;
        }

        // L'etablissement d'origine n'a de sens que pour un transfert :
        // le conserver apres un changement de statut laisserait une donnee
        // trompeuse sur la fiche.
        $statut = $data['statut_inscription'] ?? null;

        if ($statut !== null && $statut !== StatutInscriptionEleveEnum::TRANSFERE->value) {
            $data['etablissement_origine'] = null;
        }

        return $data;
    }

    /**
     * Un tuteur est partage par la fratrie : lorsqu'un NIN deja connu est
     * fourni, on met a jour la fiche existante au lieu d'en creer un doublon.
     * $tuteurActuelId permet, en modification, de reutiliser la fiche deja
     * rattachee a l'eleve quand aucun NIN ne permet de l'identifier.
     */
    private function resoudreTuteur(?array $tuteur, ?int $tuteurActuelId = null, array $eleveData = []): ?int
    {
        if (blank($tuteur)) {
            return $tuteurActuelId;
        }

        // Le pere ou la mere est le tuteur : ses coordonnees vivent dans le
        // bloc parent, on ne les redemande pas une seconde fois.
        $tuteur = $this->completerDepuisParent($tuteur, $eleveData);

        // Le telephone sert d'identifiant de connexion aux familles : il est
        // range sous une forme unique des l'enregistrement, faute de quoi deux
        // ecritures du meme numero donneraient deux tuteurs distincts.
        foreach (['telephone_principal', 'telephone_secondaire'] as $champ) {
            if (!empty($tuteur[$champ])) {
                $tuteur[$champ] = Tuteur::normaliserTelephone($tuteur[$champ]);
            }
        }

        if (!empty($tuteur['id'])) {
            $existant = $this->tuteurRepository->findById($tuteur['id']);

            return $this->tuteurRepository->update(
                $existant,
                $this->champsSoumis($tuteur),
            )->id;
        }

        if (!empty($tuteur['nin']) && $existant = $this->tuteurRepository->findByNin($tuteur['nin'])) {
            return $this->tuteurRepository->update(
                $existant,
                $this->champsSoumis($tuteur),
            )->id;
        }

        if ($tuteurActuelId !== null) {
            $existant = $this->tuteurRepository->findById($tuteurActuelId);

            return $this->tuteurRepository->update(
                $existant,
                $this->champsSoumis($tuteur),
            )->id;
        }

        return $this->tuteurRepository->create($tuteur)->id;
    }

    /**
     * Ne retient que les champs reellement soumis.
     *
     * La fiche tuteur est partagee : la corriger depuis un enfant met a jour
     * toute la fratrie, et c'est voulu — c'est l'interet d'une fiche unique.
     * Mais « corriger » suppose que le champ ait ete envoye. Une requete
     * partielle, qui n'evoque pas l'email ou la profession, ne doit pas les
     * effacer pour toute la famille au passage.
     *
     * On distingue donc l'absence (clef manquante : on ne touche pas) du vide
     * explicite (clef presente a null ou '' : l'agent a efface le champ, on
     * respecte son geste). L'identifiant, lui, ne fait que designer la fiche.
     *
     * Exception : les colonnes NOT NULL de la table. Quand le tuteur est le
     * pere ou la mere, le formulaire masque ces champs et les transmet vides ;
     * completerDepuisParent() les a normalement remplis depuis le bloc parent,
     * mais il ne le fait que si la source existe. Ce qui reste vide a ce stade
     * n'est donc pas un effacement voulu, seulement un champ jamais saisi : on
     * l'ecarte plutot que d'aller heurter la contrainte en base.
     */
    private function champsSoumis(array $tuteur): array
    {
        unset($tuteur['id']);

        foreach (self::COLONNES_TUTEUR_REQUISES as $champ) {
            if (array_key_exists($champ, $tuteur) && blank($tuteur[$champ])) {
                unset($tuteur[$champ]);
            }
        }

        return $tuteur;
    }

    /**
     * Compose la fiche tuteur a partir du bloc parent, quand le responsable
     * est le pere ou la mere.
     *
     * L'agent saisit les coordonnees du pere dans « nom_pere / prenom_pere /
     * telephone_pere » ; les redemander sous « tuteur.* » ferait saisir deux
     * fois la meme chose, avec le risque que les deux versions divergent au
     * fil des corrections. Le bloc parent fait donc foi, et la fiche tuteur en
     * est deduite.
     *
     * La fiche reste indispensable meme lorsqu'elle est ainsi derivee : c'est
     * elle que referencent les paiements, les bulletins et la messagerie. On
     * la remplit, on ne la contourne pas.
     *
     * Une valeur explicitement fournie sous « tuteur.* » n'est jamais ecrasee :
     * un tuteur peut avoir une adresse ou un telephone secondaire propres,
     * distincts de ceux notes en face du parent.
     */
    private function completerDepuisParent(array $tuteur, array $eleveData): array
    {
        $lien = $tuteur['lien_parente'] ?? null;

        $prefixe = match ($lien) {
            LienParenteEnum::PERE->value => 'pere',
            LienParenteEnum::MERE->value => 'mere',
            // Tuteur tiers : le bloc parent ne le decrit pas, il garde ses
            // propres coordonnees, deja exigees par la validation.
            default => null,
        };

        if ($prefixe === null) {
            return $tuteur;
        }

        $correspondances = [
            'nom'                 => "nom_{$prefixe}",
            'prenom'              => "prenom_{$prefixe}",
            'telephone_principal' => "telephone_{$prefixe}",
            'profession'          => "profession_{$prefixe}",
            'adresse'             => "adresse_{$prefixe}",
        ];

        foreach ($correspondances as $champTuteur => $champEleve) {
            if (blank($tuteur[$champTuteur] ?? null) && filled($eleveData[$champEleve] ?? null)) {
                $tuteur[$champTuteur] = $eleveData[$champEleve];
            }
        }

        // L'adresse du parent est souvent laissee vide parce qu'elle est celle
        // de l'eleve : on retombe dessus plutot que d'echouer sur une colonne
        // « adresse » que la table tuteurs exige.
        if (blank($tuteur['adresse'] ?? null) && filled($eleveData['adresse'] ?? null)) {
            $tuteur['adresse'] = $eleveData['adresse'];
        }

        return $tuteur;
    }
}
