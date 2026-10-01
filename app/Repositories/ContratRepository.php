<?php

namespace App\Repositories;

use App\Enums\StatutContratEnum;
use App\Interfaces\ContratRepositoryInterface;
use App\Models\Contrat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ContratRepository implements ContratRepositoryInterface
{
    /** Charge l'employe et, a travers lui, son identite. */
    private const RELATIONS = ['contractable.user', 'parent'];

    public function paginate(int $perPage, string $search, array $filtres = []): LengthAwarePaginator
    {
        return Contrat::query()
            ->with(self::RELATIONS)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_contrat', 'ilike', "%{$search}%")
                      ->orWhere('fonction', 'ilike', "%{$search}%")
                      // Chercher par nom d'employe suppose de traverser le
                      // profil puis l'utilisateur : le morph n'expose pas de
                      // jointure directe, d'ou ce passage par la relation.
                      ->orWhereHasMorph('contractable', '*', function ($sq) use ($search) {
                          $sq->where('matricule', 'ilike', "%{$search}%")
                             ->orWhereHas('user', function ($uq) use ($search) {
                                 $uq->where('first_name', 'ilike', "%{$search}%")
                                    ->orWhere('last_name', 'ilike', "%{$search}%")
                                    // Nom complet dans les deux ordres.
                                    ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"])
                                    ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$search}%"]);
                             });
                      });
                });
            })
            ->when($filtres['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->when($filtres['type_contrat'] ?? null, fn ($q, $type) => $q->where('type_contrat', $type))
            ->when($filtres['contractable_type'] ?? null, fn ($q, $type) => $q->where('contractable_type', $type))
            // Les contrats proches de leur terme, pour anticiper les
            // renouvellements : bornes a J+N a partir d'aujourd'hui.
            ->when($filtres['echeance_dans'] ?? null, function ($q, $jours) {
                $q->where('statut', StatutContratEnum::ACTIF->value)
                  ->whereNotNull('date_fin')
                  ->whereDate('date_fin', '>=', now())
                  ->whereDate('date_fin', '<=', now()->addDays((int) $jours));
            })
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): Contrat
    {
        return Contrat::with([...self::RELATIONS, 'renouvellements'])->findOrFail($id);
    }

    public function findByCodeVerification(string $code): ?Contrat
    {
        return Contrat::with(self::RELATIONS)
            ->where('code_verification', $code)
            ->first();
    }

    public function forContractable(Model $contractable): Collection
    {
        return Contrat::query()
            ->with('parent')
            ->where('contractable_type', $contractable->getMorphClass())
            ->where('contractable_id', $contractable->getKey())
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): Contrat
    {
        return Contrat::create($data)->load(self::RELATIONS);
    }

    public function update(Contrat $contrat, array $data): Contrat
    {
        $contrat->update($data);

        return $contrat->fresh(self::RELATIONS);
    }

    public function delete(Contrat $contrat): void
    {
        $contrat->delete();
    }

    /**
     * Numerote dans l'annee du contrat, pas l'annee courante : un contrat
     * anterieur saisi en retard garde une reference coherente avec sa date.
     *
     * Les contrats supprimes comptent : reutiliser leur numero ferait pointer
     * deux documents distincts vers la meme reference.
     */
    public function nextNumero(int $annee): string
    {
        $prefix = "CTR-{$annee}-";

        $last = Contrat::withTrashed()
            ->where('numero_contrat', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(numero_contrat, LENGTH('{$prefix}') + 1) AS INTEGER) DESC")
            ->value('numero_contrat');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function expirerEchus(): int
    {
        return Contrat::echus()->update([
            'statut'     => StatutContratEnum::EXPIRE->value,
            'updated_at' => now(),
        ]);
    }
}
