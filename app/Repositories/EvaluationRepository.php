<?php

namespace App\Repositories;

use App\Enums\StatutInscriptionEnum;
use App\Interfaces\EvaluationRepositoryInterface;
use App\Models\Affectation;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EvaluationRepository implements EvaluationRepositoryInterface
{
    private const RELATIONS = [
        'periode',
        'affectation.enseignant.user',
        'affectation.classeMatiere.matiere',
        'affectation.classeMatiere.classe',
    ];

    public function paginate(
        int $perPage,
        string $search,
        ?int $enseignantId = null,
        ?int $periodeId = null,
        ?int $affectationId = null,
    ): LengthAwarePaginator {
        return Evaluation::query()
            ->with(self::RELATIONS)
            ->withCount('notes')
            ->when($enseignantId, fn ($q, $id) => $q
                ->whereHas('affectation', fn ($q) => $q->where('enseignant_id', $id)))
            ->when($periodeId, fn ($q, $id) => $q->where('periode_id', $id))
            ->when($affectationId, fn ($q, $id) => $q->where('affectation_id', $id))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('titre', 'ilike', "%{$search}%")
                  ->orWhereHas('affectation.classeMatiere.matiere', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"))
                  ->orWhereHas('affectation.classeMatiere.classe', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->latest('date_evaluation')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): Evaluation
    {
        return Evaluation::with(self::RELATIONS)->findOrFail($id);
    }

    public function findWithNotes(int|string $id): Evaluation
    {
        return Evaluation::with([...self::RELATIONS, 'notes'])->findOrFail($id);
    }

    public function findAffectation(int|string $affectationId): Affectation
    {
        return Affectation::with(['classeMatiere.classe', 'classeMatiere.matiere', 'enseignant.user'])
            ->findOrFail($affectationId);
    }

    public function affectations(?int $enseignantId = null): Collection
    {
        return Affectation::query()
            ->with(['classeMatiere.matiere', 'classeMatiere.classe', 'enseignant.user'])
            ->when($enseignantId, fn ($q, $id) => $q->where('enseignant_id', $id))
            ->get()
            ->sortBy(fn ($a) => [$a->classeMatiere?->classe?->nom, $a->classeMatiere?->matiere?->nom])
            ->values();
    }

    public function existsForType(
        int|string $affectationId,
        int|string $periodeId,
        string $type,
        int|string|null $exceptId = null,
    ): bool {
        return Evaluation::query()
            ->where('affectation_id', $affectationId)
            ->where('periode_id', $periodeId)
            ->where('type', $type)
            ->when($exceptId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();
    }

    public function create(array $data): Evaluation
    {
        $evaluation = Evaluation::create($data);

        return $evaluation->fresh(self::RELATIONS);
    }

    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        $evaluation->update($data);

        return $evaluation->fresh(self::RELATIONS);
    }

    public function delete(Evaluation $evaluation): void
    {
        $evaluation->delete();
    }

    public function elevesForAffectation(Affectation $affectation): Collection
    {
        $classeId = $affectation->classeMatiere?->classe_id;

        if ($classeId === null) {
            return new Collection();
        }

        return Eleve::query()
            ->whereHas('inscriptions', fn ($q) => $q
                ->where('classe_id', $classeId)
                ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)
                ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function upsertNotes(Evaluation $evaluation, array $notes): void
    {
        DB::transaction(function () use ($evaluation, $notes) {
            foreach ($notes as $note) {
                Note::updateOrCreate(
                    [
                        'evaluation_id' => $evaluation->id,
                        'eleve_id'      => $note['eleve_id'],
                    ],
                    [
                        'valeur'       => $note['absent'] ?? false ? null : ($note['valeur'] ?? null),
                        'absent'       => $note['absent'] ?? false,
                        'appreciation' => $note['appreciation'] ?? null,
                    ],
                );
            }
        });
    }
}
