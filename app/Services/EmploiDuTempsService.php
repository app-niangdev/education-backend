<?php

namespace App\Services;

use App\Enums\JourSemaineEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ClasseRepositoryInterface;
use App\Interfaces\EmploiDuTempsRepositoryInterface;
use App\Interfaces\EmploiDuTempsServiceInterface;
use App\Models\Affectation;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmploiDuTempsService implements EmploiDuTempsServiceInterface
{
    public function __construct(
        private readonly EmploiDuTempsRepositoryInterface $repository,
        private readonly ClasseRepositoryInterface        $classeRepository,
        private readonly ActivityLogServiceInterface      $activityLog,
    ) {}

    public function forClasse(int|string $classeId): Collection
    {
        return $this->repository->forClasse($classeId);
    }

    public function find(int|string $id): EmploiDuTemps
    {
        return $this->repository->findById($id);
    }

    public function classe(int|string $classeId): Classe
    {
        return $this->classeRepository->findById($classeId);
    }

    public function create(array $data, User $authUser): EmploiDuTemps
    {
        $affectation = $this->resolveAffectation($data['affectation_id']);

        // Un creneau ne peut planifier qu'une affectation de la classe visee.
        if ((int) $affectation->classeMatiere->classe_id !== (int) $data['classe_id']) {
            abort(422, "L'affectation choisie n'appartient pas à cette classe.");
        }

        $this->assertNoConflit(
            classeId: $data['classe_id'],
            enseignantId: $affectation->enseignant_id,
            jour: $data['jour'],
            heureDebut: $data['heure_debut'],
            heureFin: $data['heure_fin'],
        );

        $creneau = $this->repository->create($data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'emploi-du-temps',
            description: $this->describe($creneau, 'Ajout au'),
            subject:     $creneau,
            newValues:   $creneau->toArray(),
        );

        return $creneau;
    }

    public function update(int|string $id, array $data, User $authUser): EmploiDuTemps
    {
        $creneau   = $this->repository->findById($id);
        $oldValues = $creneau->toArray();

        $affectationId = $data['affectation_id'] ?? $creneau->affectation_id;
        $affectation   = $this->resolveAffectation($affectationId);

        if ((int) $affectation->classeMatiere->classe_id !== (int) $creneau->classe_id) {
            abort(422, "L'affectation choisie n'appartient pas à cette classe.");
        }

        $this->assertNoConflit(
            classeId: $creneau->classe_id,
            enseignantId: $affectation->enseignant_id,
            jour: $data['jour'] ?? $creneau->jour->value,
            heureDebut: $data['heure_debut'] ?? $creneau->heure_debut,
            heureFin: $data['heure_fin'] ?? $creneau->heure_fin,
            ignoreId: $creneau->id,
        );

        $creneau = $this->repository->update($creneau, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'emploi-du-temps',
            description: $this->describe($creneau, 'Modification du créneau du'),
            subject:     $creneau,
            oldValues:   $oldValues,
            newValues:   $creneau->toArray(),
        );

        return $creneau;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $creneau = $this->repository->findById($id);

        $this->repository->delete($creneau);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'emploi-du-temps',
            description: $this->describe($creneau, 'Suppression du créneau du'),
            subject:     $creneau,
            oldValues:   $creneau->toArray(),
        );
    }

    public function dataForPdf(int|string $classeId): array
    {
        $classe   = $this->classeRepository->findById($classeId);
        $creneaux = $this->repository->forClasse($classeId);

        // Groupe par jour dans l'ordre de la semaine pour un rendu tabulaire.
        $parJour = [];
        foreach (JourSemaineEnum::cases() as $jour) {
            $duJour = $creneaux->filter(fn (EmploiDuTemps $c) => $c->jour === $jour)->values();
            if ($duJour->isNotEmpty()) {
                $parJour[$jour->libelle()] = $duJour;
            }
        }

        return [
            'classe'   => $classe,
            'parJour'  => $parJour,
            'creneaux' => $creneaux,
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function resolveAffectation(int|string $affectationId): Affectation
    {
        return Affectation::with(['classeMatiere.matiere', 'enseignant.user'])
            ->findOrFail($affectationId);
    }

    private function assertNoConflit(
        int|string $classeId,
        int|string $enseignantId,
        string $jour,
        string $heureDebut,
        string $heureFin,
        ?int $ignoreId = null,
    ): void {
        if ($heureFin <= $heureDebut) {
            abort(422, "L'heure de fin doit être postérieure à l'heure de début.");
        }

        $jourEnum = JourSemaineEnum::from($jour);

        if ($this->repository->classeConflit($classeId, $jourEnum, $heureDebut, $heureFin, $ignoreId)) {
            abort(422, "La classe a déjà un cours sur ce créneau horaire.");
        }

        if ($this->repository->enseignantConflit($enseignantId, $jourEnum, $heureDebut, $heureFin, $ignoreId)) {
            abort(422, "L'enseignant est déjà occupé sur ce créneau horaire.");
        }
    }

    private function describe(EmploiDuTemps $creneau, string $prefix): string
    {
        $matiere = $creneau->affectation?->classeMatiere?->matiere?->nom ?? 'cours';
        $jour    = $creneau->jour->libelle();

        return "{$prefix} planning : {$matiere} le {$jour} ({$creneau->heure_debut} - {$creneau->heure_fin})";
    }
}
