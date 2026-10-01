<?php

namespace App\Services;

use App\Enums\DecisionConseilEnum;
use App\Enums\DistinctionEnum;
use App\Enums\MentionEnum;
use App\Enums\RoleEnum;
use App\Enums\StatutBulletinEnum;
use App\Enums\StatutInscriptionEleveEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\BulletinRepositoryInterface;
use App\Interfaces\BulletinServiceInterface;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Periode;
use App\Models\User;
use App\Services\Assiduite\CalculateurAssiduite;
use App\Services\Bulletin\CalculateurBulletin;
use App\Services\Bulletin\LigneCalculee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Génération, publication et consultation des bulletins.
 *
 * La génération porte toujours sur une classe entière, jamais sur un élève
 * isolé : le rang d'un élève n'a de sens que rapporté à ses camarades. Un
 * bulletin individuel n'est donc qu'une projection du calcul de sa classe.
 *
 * La publication fige le bulletin. Tant qu'il est brouillon, une régénération
 * le recalcule librement ; une fois publié il devient intouchable, car il a
 * été remis aux familles. Le retour en arrière existe mais suppose un geste
 * explicite de dépublication.
 */
class BulletinService implements BulletinServiceInterface
{
    public function __construct(
        private readonly BulletinRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface $activityLog,
        private readonly CalculateurBulletin         $calculateur,
        private readonly CalculateurAssiduite        $assiduite,
    ) {}

    public function meta(): array
    {
        return [
            'statuts'     => $this->casesToOptions(StatutBulletinEnum::cases()),
            'mentions'    => $this->casesToOptions(MentionEnum::cases()),
            'decisions'   => $this->casesToOptions(DecisionConseilEnum::cases()),
            'distinctions'=> $this->casesToOptions(DistinctionEnum::cases()),
        ];
    }

    public function list(
        User $authUser,
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $periodeId = null,
        ?string $statut = null,
    ): LengthAwarePaginator {
        $this->assertPeutConsulter($authUser);

        return $this->repository->paginate(
            perPage:   $perPage,
            search:    $search,
            classeId:  $classeId,
            periodeId: $periodeId,
            statut:    $statut,
            classeIds: $this->classeIdsIfTeacher($authUser),
        );
    }

    public function find(User $authUser, int|string $id): Bulletin
    {
        $bulletin = $this->repository->findById($id);
        $this->assertPeutConsulter($authUser, $bulletin);

        return $bulletin;
    }

    // ------------------------------------------------------------------
    // Génération
    // ------------------------------------------------------------------

    public function genererPourClasse(int|string $classeId, int|string $periodeId, User $authUser): array
    {
        $classe  = $this->repository->findClasse($classeId);
        $periode = $this->repository->findPeriode($periodeId);

        $this->assertPeriodeCoherente($classe, $periode);

        if ($this->repository->existePublie($classeId, $periodeId)) {
            abort(422, "Des bulletins de cette classe sont déjà publiés pour cette période. Dépubliez-les avant de régénérer.");
        }

        $programme = $this->repository->programmeDeClasse($classeId);

        if ($programme->isEmpty()) {
            abort(422, "Cette classe n'a aucune matière à son programme.");
        }

        $eleves = $this->repository->elevesForClasse($classeId);

        if ($eleves->isEmpty()) {
            abort(422, "Aucun élève inscrit dans cette classe pour l'année en cours.");
        }

        $evaluations = $this->repository->evaluationsAvecNotes($classeId, $periodeId);

        // 1. Calculer les lignes de chaque élève.
        $lignesParEleve = [];
        $agregatsParEleve = [];

        foreach ($eleves as $eleve) {
            $lignes = $this->calculateur->lignesPourEleve((int) $eleve->id, $programme, $evaluations);

            $lignesParEleve[(int) $eleve->id]   = $lignes;
            $agregatsParEleve[(int) $eleve->id] = $this->calculateur->agregats($lignes);
        }

        // 2. Classer : les rangs ne peuvent s'établir qu'une fois toute la
        //    classe calculée.
        $rangsMatiere = $this->calculateur->rangsParMatiere($lignesParEleve);
        $rangsGeneraux = $this->calculateur->rangGeneral(
            array_map(fn (array $a) => $a['moyenne_generale'], $agregatsParEleve)
        );

        // 3. L'assiduité de la période, en une seule requête pour la classe.
        //    Tableau vide si aucun appel n'a jamais été fait : le bulletin
        //    affichera « — » plutôt qu'un 0 qui laisserait croire à une
        //    assiduité parfaite.
        $assiduiteParEleve = $this->assiduite->pourClasseEtPeriode((int) $classe->id, (int) $periode->id);

        // 4. Composer les enregistrements.
        $effectif = $eleves->count();
        $donnees  = [];

        foreach ($eleves as $eleve) {
            $eleveId  = (int) $eleve->id;
            $agregats = $agregatsParEleve[$eleveId];
            $rang     = $rangsGeneraux[$eleveId] ?? null;

            $donnees[] = [
                'bulletin' => $this->attributsBulletin(
                    $eleve,
                    $classe,
                    $periode,
                    $agregats,
                    $rang,
                    $effectif,
                    $this->assiduite->pourEleve($assiduiteParEleve, $eleveId),
                ),
                'lignes'   => $this->attributsLignes($lignesParEleve[$eleveId], $rangsMatiere[$eleveId] ?? []),
            ];
        }

        $bulletins = $this->repository->enregistrerLot($donnees);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'bulletins',
            description: "Génération des bulletins de la classe « {$classe->nom} » — {$periode->libelle} ({$effectif} élève(s))",
            subject:     $classe,
        );

        return [
            'bulletins'             => $bulletins,
            'classe'                => $classe,
            'periode'               => $periode,
            'effectif'              => $effectif,
            // La génération reste permise avant la clôture des notes : le chef
            // d'établissement veut pouvoir consulter des brouillons. On le
            // signale pour que l'interface prévienne d'un résultat provisoire.
            'saisie_encore_ouverte' => !$periode->saisieNotesFermee(),
        ];
    }

    /**
     * @param array{absences:?int, retards:?int} $assiduite heures d'absence et
     *        nombre de retards sur la période, null si aucun appel n'a été fait
     */
    private function attributsBulletin(
        Eleve $eleve,
        Classe $classe,
        Periode $periode,
        array $agregats,
        ?array $rang,
        int $effectif,
        array $assiduite = ['absences' => null, 'retards' => null],
    ): array {
        return [
            'eleve_id'             => $eleve->id,
            'classe_id'            => $classe->id,
            'periode_id'           => $periode->id,
            'annee_scolaire_id'    => $classe->annee_scolaire_id,
            'statut'               => StatutBulletinEnum::BROUILLON,

            // L'identité est recopiée : le bulletin doit rester lisible tel
            // qu'il a été édité, même si la fiche élève évolue ensuite.
            'eleve_nom'            => $eleve->nom,
            'eleve_prenom'         => $eleve->prenom,
            'eleve_matricule'      => $eleve->matricule,
            'eleve_date_naissance' => $eleve->date_naissance,
            'eleve_lieu_naissance' => $eleve->lieu_naissance,
            'classe_nom'           => $classe->nom,
            'classe_redoublee'     => $eleve->statut_inscription === StatutInscriptionEleveEnum::REDOUBLANT,
            'effectif_classe'      => $effectif,

            'total_coefficients'   => $agregats['total_coefficients'],
            'total_points'         => $agregats['total_points'],
            'moyenne_generale'     => $agregats['moyenne_generale'],
            'rang'                 => $rang['rang'] ?? null,
            'rang_ex_aequo'        => $rang['ex_aequo'] ?? false,
            'mention'              => $this->calculateur->mentionPour($agregats['moyenne_generale']),

            // Alimentées par le module d'assiduité : heures d'absence et
            // nombre de retards relevés sur la période.
            'absences'             => $assiduite['absences'],
            'retards'              => $assiduite['retards'],

            'genere_le'            => Carbon::now(),
        ];
    }

    /**
     * @param  array<int, LigneCalculee>                    $lignes
     * @param  array<int, array{rang:int, ex_aequo:bool}>   $rangs
     */
    private function attributsLignes(array $lignes, array $rangs): array
    {
        $resultat = [];

        foreach ($lignes as $cle => $ligne) {
            $rang = $rangs[$cle] ?? null;

            $resultat[] = [
                'matiere_id'    => $ligne->matiereId,
                'matiere_nom'   => $ligne->matiereNom,
                'ordre'         => $ligne->ordre,
                'moy_devoirs'   => $ligne->moyDevoirs,
                'composition'   => $ligne->composition,
                'moyenne'       => $ligne->moyenne,
                'coefficient'   => $ligne->coefficient,
                'moy_x_coef'    => $ligne->moyXCoef,
                'rang'          => $rang['rang'] ?? null,
                'rang_ex_aequo' => $rang['ex_aequo'] ?? false,
                'appreciation'  => $ligne->appreciation(),
                'notee'         => $ligne->notee,
            ];
        }

        return $resultat;
    }

    // ------------------------------------------------------------------
    // Conseil de classe
    // ------------------------------------------------------------------

    public function updateConseil(int|string $id, array $data, User $authUser): Bulletin
    {
        $bulletin = $this->repository->findById($id);

        if ($bulletin->estPublie()) {
            abort(422, "Ce bulletin est publié : dépubliez-le pour modifier le conseil de classe.");
        }

        $anciennesValeurs = $bulletin->only(['decision_conseil', 'distinction', 'observations']);
        $bulletin         = $this->repository->updateConseil($bulletin, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'bulletins',
            description: "Conseil de classe saisi sur le bulletin de {$bulletin->nom_complet} ({$bulletin->classe_nom})",
            subject:     $bulletin,
            oldValues:   $anciennesValeurs,
            newValues:   $bulletin->only(['decision_conseil', 'distinction', 'observations']),
        );

        return $bulletin;
    }

    // ------------------------------------------------------------------
    // Publication
    // ------------------------------------------------------------------

    public function publierClasse(int|string $classeId, int|string $periodeId, User $authUser): int
    {
        $classe  = $this->repository->findClasse($classeId);
        $periode = $this->repository->findPeriode($periodeId);

        $brouillons = $this->repository
            ->forClasseEtPeriode($classeId, $periodeId)
            ->filter(fn (Bulletin $b) => !$b->estPublie());

        if ($brouillons->isEmpty()) {
            abort(422, "Aucun bulletin à publier pour cette classe sur cette période.");
        }

        $nombre = $this->repository->publier($brouillons, (int) $authUser->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'bulletins',
            description: "Publication de {$nombre} bulletin(s) — classe « {$classe->nom} », {$periode->libelle}",
            subject:     $classe,
        );

        return $nombre;
    }

    public function publierUn(int|string $id, User $authUser): Bulletin
    {
        $bulletin = $this->repository->findById($id);

        if ($bulletin->estPublie()) {
            abort(422, "Ce bulletin est déjà publié.");
        }

        $this->repository->publier(collect([$bulletin]), (int) $authUser->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'bulletins',
            description: "Publication du bulletin de {$bulletin->nom_complet} ({$bulletin->classe_nom})",
            subject:     $bulletin,
        );

        return $this->repository->findById($id);
    }

    public function depublierClasse(int|string $classeId, int|string $periodeId, User $authUser): int
    {
        $classe  = $this->repository->findClasse($classeId);
        $periode = $this->repository->findPeriode($periodeId);

        $publies = $this->repository
            ->forClasseEtPeriode($classeId, $periodeId)
            ->filter(fn (Bulletin $b) => $b->estPublie());

        if ($publies->isEmpty()) {
            abort(422, "Aucun bulletin publié pour cette classe sur cette période.");
        }

        $nombre = $this->repository->depublier($publies);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'bulletins',
            description: "Dépublication de {$nombre} bulletin(s) — classe « {$classe->nom} », {$periode->libelle}",
            subject:     $classe,
        );

        return $nombre;
    }

    public function depublierUn(int|string $id, User $authUser): Bulletin
    {
        $bulletin = $this->repository->findById($id);

        if (!$bulletin->estPublie()) {
            abort(422, "Ce bulletin n'est pas publié.");
        }

        $this->repository->depublier(collect([$bulletin]));

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'bulletins',
            description: "Dépublication du bulletin de {$bulletin->nom_complet} ({$bulletin->classe_nom})",
            subject:     $bulletin,
        );

        return $this->repository->findById($id);
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    public function dataForPdf(User $authUser, int|string $id): array
    {
        $bulletin = $this->find($authUser, $id);

        return ['bulletin' => $bulletin];
    }

    public function dataForPdfClasse(User $authUser, int|string $classeId, int|string $periodeId): array
    {
        $this->assertPeutConsulter($authUser);

        $classe    = $this->repository->findClasse($classeId);
        $periode   = $this->repository->findPeriode($periodeId);
        $bulletins = $this->repository->forClasseEtPeriode($classeId, $periodeId);

        if ($bulletins->isEmpty()) {
            abort(422, "Aucun bulletin généré pour cette classe sur cette période.");
        }

        return [
            'bulletins' => $bulletins,
            'classe'    => $classe,
            'periode'   => $periode,
        ];
    }

    // ------------------------------------------------------------------
    // Autorisations
    // ------------------------------------------------------------------

    private function isTeacher(User $user): bool
    {
        return $user->role_id === RoleEnum::Teacher->value;
    }

    /**
     * Les classes visibles par un enseignant, via ses affectations.
     * null pour les autres rôles : aucune restriction.
     */
    private function classeIdsIfTeacher(User $user): ?array
    {
        if (!$this->isTeacher($user)) {
            return null;
        }

        $enseignantId = $user->enseignant?->id;

        if ($enseignantId === null) {
            return [-1]; // prof sans profil enseignant : aucune classe
        }

        return Classe::query()
            ->whereHas('classeMatieres.affectation', fn ($q) => $q->where('enseignant_id', $enseignantId))
            ->pluck('id')
            ->all();
    }

    /**
     * Le trésorier n'a aucun motif d'accéder aux résultats scolaires ; les
     * autres rôles consultent, l'enseignant se limitant à ses classes.
     */
    private function assertPeutConsulter(User $user, ?Bulletin $bulletin = null): void
    {
        if ($user->role_id === RoleEnum::Treasurer->value) {
            abort(403, "Accès non autorisé.");
        }

        if ($bulletin === null) {
            return;
        }

        $classeIds = $this->classeIdsIfTeacher($user);

        if ($classeIds !== null && !in_array((int) $bulletin->classe_id, $classeIds, true)) {
            abort(403, "Vous ne pouvez consulter que les bulletins de vos propres classes.");
        }
    }

    private function assertPeriodeCoherente(Classe $classe, Periode $periode): void
    {
        if ((int) $periode->annee_scolaire_id !== (int) $classe->annee_scolaire_id) {
            abort(422, "La période sélectionnée n'appartient pas à l'année scolaire de cette classe.");
        }
    }

    /** @param array<int, \BackedEnum> $cases */
    private function casesToOptions(array $cases): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->libelle()],
            $cases,
        );
    }
}
