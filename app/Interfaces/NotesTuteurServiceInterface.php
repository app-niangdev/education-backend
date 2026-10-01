<?php

namespace App\Interfaces;

use App\Models\User;

/**
 * Les resultats scolaires vus par la famille.
 */
interface NotesTuteurServiceInterface
{
    /**
     * Les eleves rattaches au compte connecte, avec leur classe.
     *
     * Un tuteur suit souvent plusieurs enfants : c'est cette liste qui alimente
     * le selecteur de l'ecran.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mesEleves(User $user): array;

    /**
     * Le releve de notes d'un eleve pour une periode.
     *
     * @param  int|string|null  $periodeId  la periode en cours si rien n'est demande
     * @return array<string, mixed>
     */
    public function relevePourEleve(User $user, int|string $eleveId, int|string|null $periodeId = null): array;
}
