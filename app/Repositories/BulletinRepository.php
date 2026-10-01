<?php

namespace App\Repositories;

use App\Enums\StatutBulletinEnum;
use App\Enums\StatutInscriptionEnum;
use App\Interfaces\BulletinRepositoryInterface;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BulletinRepository implements BulletinRepositoryInterface
{
    private const RELATIONS = [
        'eleve',
        'classe',
        'periode',
        'anneeScolaire',
        'publiePar',
    ];

    /** Les colonnes du conseil de classe : jamais écrasées par un recalcul. */
    private const COLONNES_CONSEIL = [
        'decision_conseil',
        'distinction',
        'observations',
    ];

    public function paginate(
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $periodeId = null,
        ?string $statut = null,
        ?array $classeIds = null,
    ): LengthAwarePaginator {
        return Bulletin::query()
            ->with(self::RELATIONS)
            ->withCount('lignes')
            ->when($classeId, fn ($q, $id) => $q->where('classe_id', $id))
            ->when($periodeId, fn ($q, $id) => $q->where('periode_id', $id))
            ->when($statut, fn ($q, $s) => $q->where('statut', $s))
            // Vue enseignant : bornée à ses classes. Un tableau vide ne doit
            // rien renvoyer, d'où le test sur null et non sur le vide.
            ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds))
            // La recherche porte sur l'identité figée : c'est ce qui est imprimé.
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('eleve_nom', 'ilike', "%{$search}%")
                  ->orWhere('eleve_prenom', 'ilike', "%{$search}%")
                  ->orWhere('eleve_matricule', 'ilike', "%{$search}%")
                  ->orWhere('classe_nom', 'ilike', "%{$search}%");
            }))
            ->orderBy('classe_nom')
            ->orderByRaw('rang IS NULL')   // les non-classés en fin de liste
            ->orderBy('rang')
            ->orderBy('eleve_nom')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): Bulletin
    {
        return Bulletin::with([...self::RELATIONS, 'lignes.matiere'])->findOrFail($id);
    }

    public function findClasse(int|string $classeId): Classe
    {
        return Classe::with(['niveau', 'anneeScolaire'])->findOrFail($classeId);
    }

    public function findPeriode(int|string $periodeId): Periode
    {
        return Periode::with('anneeScolaire')->findOrFail($periodeId);
    }

    public function forClasseEtPeriode(int|string $classeId, int|string $periodeId): Collection
    {
        return Bulletin::query()
            ->with([...self::RELATIONS, 'lignes.matiere'])
            ->where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->orderByRaw('rang IS NULL')
            ->orderBy('rang')
            ->orderBy('eleve_nom')
            ->orderBy('eleve_prenom')
            ->get();
    }

    /**
     * Même règle que EvaluationRepository::elevesForAffectation() : seuls les
     * élèves dont l'inscription est validée sur l'année en cours composent
     * la classe.
     */
    public function elevesForClasse(int|string $classeId): Collection
    {
        return Eleve::query()
            ->whereHas('inscriptions', fn ($q) => $q
                ->where('classe_id', $classeId)
                ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)
                ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function programmeDeClasse(int|string $classeId): Collection
    {
        return ClasseMatiere::query()
            ->with('matiere')
            ->where('classe_id', $classeId)
            ->get()
            // L'ordre du programme, tel que réglé par l'établissement. Le nom
            // départage les matières qu'on n'a pas encore classées (ordre 0).
            ->sortBy(fn (ClasseMatiere $cm) => [$cm->ordre ?? 0, $cm->matiere?->nom ?? ''])
            ->values();
    }

    public function evaluationsAvecNotes(int|string $classeId, int|string $periodeId): Collection
    {
        return Evaluation::query()
            ->with(['notes', 'affectation'])
            ->where('periode_id', $periodeId)
            ->whereHas('affectation.classeMatiere', fn ($q) => $q->where('classe_id', $classeId))
            ->get();
    }

    public function existePublie(int|string $classeId, int|string $periodeId): bool
    {
        return Bulletin::query()
            ->where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->where('statut', StatutBulletinEnum::PUBLIE)
            ->exists();
    }

    public function enregistrerLot(array $donnees): Collection
    {
        $ids = DB::transaction(function () use ($donnees) {
            $ids = [];

            foreach ($donnees as $entree) {
                $attributs = $entree['bulletin'];

                $bulletin = Bulletin::query()
                    ->where('eleve_id', $attributs['eleve_id'])
                    ->where('periode_id', $attributs['periode_id'])
                    ->first();

                if ($bulletin === null) {
                    $bulletin = Bulletin::create($attributs);
                } else {
                    // Le travail du conseil de classe survit à un recalcul :
                    // il n'est pas dérivé des notes, il est saisi à la main.
                    $bulletin->update(array_diff_key($attributs, array_flip(self::COLONNES_CONSEIL)));
                }

                // Les lignes sont reconstruites : le programme de la classe a
                // pu changer entre deux générations (matière ajoutée, retirée).
                BulletinLigne::where('bulletin_id', $bulletin->id)->forceDelete();

                foreach ($entree['lignes'] as $ligne) {
                    BulletinLigne::create([...$ligne, 'bulletin_id' => $bulletin->id]);
                }

                $ids[] = $bulletin->id;
            }

            return $ids;
        });

        return Bulletin::with([...self::RELATIONS, 'lignes.matiere'])
            ->whereIn('id', $ids)
            ->orderByRaw('rang IS NULL')
            ->orderBy('rang')
            ->orderBy('eleve_nom')
            ->get();
    }

    public function updateConseil(Bulletin $bulletin, array $data): Bulletin
    {
        $bulletin->update($data);

        return $bulletin->fresh([...self::RELATIONS, 'lignes.matiere']);
    }

    public function publier(Collection $bulletins, int $userId): int
    {
        if ($bulletins->isEmpty()) {
            return 0;
        }

        return Bulletin::whereIn('id', $bulletins->pluck('id'))->update([
            'statut'     => StatutBulletinEnum::PUBLIE,
            'publie_par' => $userId,
            'publie_le'  => Carbon::now(),
        ]);
    }

    public function depublier(Collection $bulletins): int
    {
        if ($bulletins->isEmpty()) {
            return 0;
        }

        return Bulletin::whereIn('id', $bulletins->pluck('id'))->update([
            'statut'     => StatutBulletinEnum::BROUILLON,
            'publie_par' => null,
            'publie_le'  => null,
        ]);
    }
}
