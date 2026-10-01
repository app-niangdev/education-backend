<?php

namespace App\Interfaces;

use App\Models\Tuteur;
use Illuminate\Database\Eloquent\Collection;

interface TuteurRepositoryInterface
{
    public function all(): Collection;

    public function findById(int|string $id): Tuteur;

    public function findByNin(string $nin): ?Tuteur;

    public function rechercher(string $recherche, int $limite = 10): Collection;

    public function create(array $data): Tuteur;

    public function update(Tuteur $tuteur, array $data): Tuteur;
}
