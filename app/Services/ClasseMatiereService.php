<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ClasseMatiereRepositoryInterface;
use App\Interfaces\ClasseMatiereServiceInterface;
use App\Models\ClasseMatiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClasseMatiereService implements ClasseMatiereServiceInterface
{
    public function __construct(
        private readonly ClasseMatiereRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface      $activityLog,
    ) {}

    public function forClasse(int|string $classeId): Collection
    {
        return $this->repository->forClasse($classeId);
    }

    public function find(int|string $id): ClasseMatiere
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): ClasseMatiere
    {
        if ($this->repository->existsForClasseAndMatiere($data['classe_id'], $data['matiere_id'])) {
            abort(422, 'Cette matière est déjà inscrite au programme de cette classe.');
        }

        // Une nouvelle matière se place en fin de programme : à l'école de la
        // remonter là où elle doit être.
        $data['ordre'] = $this->repository->prochainOrdre($data['classe_id']);

        $classeMatiere = $this->repository->create($data);
        $classeMatiere = $this->repository->findById($classeMatiere->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'classe-matieres',
            description: "Ajout de la matière « {$classeMatiere->matiere?->nom} » (coefficient {$classeMatiere->coefficient}) au programme de la classe « {$classeMatiere->classe?->nom} »",
            subject:     $classeMatiere,
            newValues:   $classeMatiere->toArray(),
        );

        return $classeMatiere;
    }

    public function update(int|string $id, array $data, User $authUser): ClasseMatiere
    {
        $classeMatiere = $this->repository->findById($id);
        $oldValues     = $classeMatiere->toArray();

        // Seuls le coefficient et le volume horaire sont modifiables ; le couple
        // classe/matière est structurel (on supprime et on recrée sinon).
        $classeMatiere = $this->repository->update($classeMatiere, [
            'coefficient'    => $data['coefficient'] ?? $classeMatiere->coefficient,
            'volume_horaire' => $data['volume_horaire'] ?? $classeMatiere->volume_horaire,
        ]);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'classe-matieres',
            description: "Modification du coefficient de « {$classeMatiere->matiere?->nom} » en « {$classeMatiere->classe?->nom} » (coefficient {$classeMatiere->coefficient})",
            subject:     $classeMatiere,
            oldValues:   $oldValues,
            newValues:   $classeMatiere->toArray(),
        );

        return $classeMatiere;
    }

    public function reordonner(int|string $classeId, array $ids, User $authUser): Collection
    {
        $programme = $this->repository->forClasse($classeId);

        if ($programme->isEmpty()) {
            abort(422, "Cette classe n'a aucune matière à son programme.");
        }

        // La liste reçue doit décrire le programme exactement : ni matière
        // étrangère, ni matière oubliée, sans quoi l'ordre serait incohérent.
        $attendus = $programme->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $recus    = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($attendus !== $recus) {
            abort(422, "La liste des matières ne correspond pas au programme de cette classe.");
        }

        // La position vient du rang dans la liste reçue.
        $ordres = [];
        foreach (array_values($ids) as $position => $id) {
            $ordres[(int) $id] = $position + 1;
        }

        $this->repository->reordonner($ordres);

        $classe = $programme->first()?->classe;

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'classe-matieres',
            description: "Réorganisation du programme de la classe « {$classe?->nom} » (" . count($ids) . ' matière(s))',
            subject:     $classe,
        );

        return $this->repository->forClasse($classeId);
    }

    public function delete(int|string $id, User $authUser): void
    {
        $classeMatiere = $this->repository->findById($id);

        if ($this->repository->isUsed($classeMatiere)) {
            abort(422, "Impossible de retirer cette matière du programme : un enseignant y est affecté. Retirez d'abord l'affectation.");
        }

        $this->repository->delete($classeMatiere);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'classe-matieres',
            description: "Retrait de la matière « {$classeMatiere->matiere?->nom} » du programme de la classe « {$classeMatiere->classe?->nom} »",
            subject:     $classeMatiere,
            oldValues:   $classeMatiere->toArray(),
        );
    }
}
