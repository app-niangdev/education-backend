<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\TypeEvaluationEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\EvaluationRepositoryInterface;
use App\Interfaces\EvaluationServiceInterface;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Parametrage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EvaluationService implements EvaluationServiceInterface
{
    public function __construct(
        private readonly EvaluationRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface   $activityLog,
    ) {}

    public function affectationsFor(User $authUser): Collection
    {
        return $this->repository->affectations($this->enseignantIdIfTeacher($authUser));
    }

    public function meta(): array
    {
        return [
            'bareme_defaut' => Parametrage::baremeDefaut(),
            'types'         => array_map(
                fn (TypeEvaluationEnum $t) => ['value' => $t->value, 'label' => $t->libelle()],
                TypeEvaluationEnum::cases(),
            ),
        ];
    }

    public function list(
        User $authUser,
        int $perPage,
        string $search,
        ?int $periodeId = null,
        ?int $affectationId = null,
    ): LengthAwarePaginator {
        // Un enseignant ne voit que ses propres évaluations ; sinon vue globale.
        $enseignantId = $this->enseignantIdIfTeacher($authUser);

        return $this->repository->paginate(
            perPage:       $perPage,
            search:        $search,
            enseignantId:  $enseignantId,
            periodeId:     $periodeId,
            affectationId: $affectationId,
        );
    }

    public function find(User $authUser, int|string $id): Evaluation
    {
        $evaluation = $this->repository->findById($id);

        $this->assertCanAccess($authUser, $evaluation);

        return $evaluation;
    }

    public function gradeSheet(User $authUser, int|string $id): array
    {
        $evaluation = $this->repository->findWithNotes($id);

        $this->assertCanAccess($authUser, $evaluation);

        $eleves      = $this->repository->elevesForAffectation($evaluation->affectation);
        $notesByEleve = $evaluation->notes->keyBy('eleve_id');

        $lignes = $eleves->map(fn ($eleve) => [
            'eleve_id'     => $eleve->id,
            'matricule'    => $eleve->matricule,
            'nom_complet'  => $eleve->nom_complet,
            'valeur'       => $notesByEleve->get($eleve->id)?->valeur,
            'absent'       => (bool) ($notesByEleve->get($eleve->id)?->absent ?? false),
            'appreciation' => $notesByEleve->get($eleve->id)?->appreciation,
        ])->values();

        return [
            'evaluation'    => $evaluation->makeHidden('notes'),
            'saisie_fermee' => $evaluation->periode?->saisieNotesFermee() ?? false,
            'lignes'        => $lignes,
        ];
    }

    public function create(array $data, User $authUser): Evaluation
    {
        $affectation = $this->repository->findAffectation($data['affectation_id']);

        $this->assertOwnsAffectation($authUser, $affectation);

        // Cadre imposé : un seul Devoir 1 / Devoir 2 / Composition par
        // (matière × période).
        if ($this->repository->existsForType($data['affectation_id'], $data['periode_id'], $data['type'])) {
            $libelle = TypeEvaluationEnum::from($data['type'])->libelle();
            abort(422, "Une évaluation « {$libelle} » existe déjà pour cette matière sur cette période.");
        }

        // Le barème par défaut est un paramètre de l'établissement.
        if (!isset($data['bareme'])) {
            $data['bareme'] = Parametrage::baremeDefaut();
        }

        // Le coefficient n'est plus saisi : il est déduit de classe_matiere.
        unset($data['coefficient']);

        $evaluation = $this->repository->create($data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'evaluations',
            description: "Création de l'évaluation « {$evaluation->titre} » ({$evaluation->affectation?->classeMatiere?->matiere?->nom} — {$evaluation->affectation?->classeMatiere?->classe?->nom})",
            subject:     $evaluation,
            newValues:   $evaluation->toArray(),
        );

        return $evaluation;
    }

    public function update(int|string $id, array $data, User $authUser): Evaluation
    {
        $evaluation = $this->repository->findById($id);

        $this->assertCanAccess($authUser, $evaluation);

        // Réduire le barème en dessous d'une note déjà saisie casserait l'intégrité.
        if (isset($data['bareme'])) {
            $maxSaisie = $evaluation->notes()->whereNotNull('valeur')->max('valeur');
            if ($maxSaisie !== null && (float) $data['bareme'] < (float) $maxSaisie) {
                abort(422, "Le barème ne peut pas être inférieur à une note déjà saisie ({$maxSaisie}).");
            }
        }

        // Changer le type ou la période ne doit pas dupliquer le cadre imposé.
        $newType    = $data['type'] ?? $evaluation->type?->value;
        $newPeriode = $data['periode_id'] ?? $evaluation->periode_id;
        if ($this->repository->existsForType($evaluation->affectation_id, $newPeriode, $newType, $evaluation->id)) {
            $libelle = TypeEvaluationEnum::from($newType)->libelle();
            abort(422, "Une évaluation « {$libelle} » existe déjà pour cette matière sur cette période.");
        }

        // Le coefficient reste déduit de classe_matiere : on ignore toute valeur.
        unset($data['coefficient']);

        $oldValues  = $evaluation->toArray();
        $evaluation = $this->repository->update($evaluation, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'evaluations',
            description: "Modification de l'évaluation « {$evaluation->titre} »",
            subject:     $evaluation,
            oldValues:   $oldValues,
            newValues:   $evaluation->toArray(),
        );

        return $evaluation;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $evaluation = $this->repository->findById($id);

        $this->assertCanAccess($authUser, $evaluation);

        $this->repository->delete($evaluation);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'evaluations',
            description: "Suppression de l'évaluation « {$evaluation->titre} »",
            subject:     $evaluation,
            oldValues:   $evaluation->toArray(),
        );
    }

    public function saveNotes(int|string $id, array $notes, User $authUser): array
    {
        $evaluation = $this->repository->findById($id);

        $this->assertCanAccess($authUser, $evaluation);

        if ($evaluation->periode?->saisieNotesFermee()) {
            abort(422, "La saisie des notes est close pour cette période (date limite dépassée).");
        }

        // Les notes ne peuvent concerner que des élèves réellement inscrits dans la classe.
        $elevesAutorises = $this->repository
            ->elevesForAffectation($evaluation->affectation)
            ->pluck('id')
            ->all();

        foreach ($notes as $note) {
            if (!in_array((int) $note['eleve_id'], $elevesAutorises, true)) {
                abort(422, "Un élève ne fait pas partie de cette classe (id {$note['eleve_id']}).");
            }

            $absent = $note['absent'] ?? false;
            $valeur = $note['valeur'] ?? null;

            if (!$absent && $valeur !== null && (float) $valeur > (float) $evaluation->bareme) {
                abort(422, "Une note ({$valeur}) dépasse le barème de l'évaluation ({$evaluation->bareme}).");
            }
        }

        $this->repository->upsertNotes($evaluation, $notes);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'notes',
            description: "Saisie des notes de l'évaluation « {$evaluation->titre} » (" . count($notes) . " élève(s))",
            subject:     $evaluation,
        );

        return $this->gradeSheet($authUser, $evaluation->id);
    }

    // ------------------------------------------------------------------
    // Autorisations
    // ------------------------------------------------------------------

    private function isTeacher(User $user): bool
    {
        return $user->role_id === RoleEnum::Teacher->value;
    }

    private function isAdminOrManager(User $user): bool
    {
        return in_array($user->role_id, [RoleEnum::Admin->value, RoleEnum::Manager->value], true);
    }

    /** L'id enseignant de l'utilisateur s'il est prof, sinon null. */
    private function enseignantIdIfTeacher(User $user): ?int
    {
        if (!$this->isTeacher($user)) {
            return null;
        }

        return $user->enseignant?->id ?? -1; // -1 : prof sans profil enseignant → aucune évaluation
    }

    private function assertOwnsAffectation(User $user, Affectation $affectation): void
    {
        if ($this->isAdminOrManager($user)) {
            return;
        }

        if ($this->isTeacher($user) && $affectation->enseignant_id === $user->enseignant?->id) {
            return;
        }

        abort(403, "Vous ne pouvez gérer que les évaluations de vos propres classes.");
    }

    private function assertCanAccess(User $user, Evaluation $evaluation): void
    {
        $this->assertOwnsAffectation($user, $evaluation->affectation);
    }
}
