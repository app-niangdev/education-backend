<?php

namespace App\Interfaces;

use App\Models\ClasseMatiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ClasseMatiereServiceInterface
{
    public function forClasse(int|string $classeId): Collection;

    public function find(int|string $id): ClasseMatiere;

    public function create(array $data, User $authUser): ClasseMatiere;

    public function update(int|string $id, array $data, User $authUser): ClasseMatiere;

    public function delete(int|string $id, User $authUser): void;

    /**
     * Fixe l'ordre d'affichage du programme, celui qu'imprimera le bulletin.
     * `$ids` liste les classe_matiere_id dans l'ordre voulu et doit décrire
     * le programme au complet.
     */
    public function reordonner(int|string $classeId, array $ids, User $authUser): Collection;
}
