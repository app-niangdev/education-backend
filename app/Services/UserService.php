<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserServiceInterface;
use App\Models\User;
use App\Services\Concerns\OuvreLeCompte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService implements UserServiceInterface
{
    use OuvreLeCompte;

    public function __construct(
        private readonly UserRepositoryInterface       $userRepository,
        private readonly PasswordResetServiceInterface $reinitialisation,
    ) {}

    public function list(int $perPage, string $search, string $searchRole): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage, $search, $searchRole);
    }

    public function find(int|string $id): User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Cree un compte et lui ouvre un acces.
     *
     * Le personnel recoit un lien : le compte nait ferme, aucun mot de passe
     * connu de l'application n'y donne acces, et son titulaire choisit le sien.
     *
     * Le tuteur fait exception. Il se connecte par telephone, et beaucoup de
     * familles n'ont pas d'adresse : lui envoyer un lien qu'il ne recevrait pas
     * reviendrait a lui creer un compte inaccessible. Son acces lui est donc
     * remis par l'etablissement — mais avec un mot de passe tire au hasard pour
     * lui seul, et non la chaine commune d'autrefois, qui ouvrait tous les
     * comptes a quiconque la connaissait. Il est renvoye a l'appelant, en clair
     * et une seule fois : c'est l'agent qui le transmet a la famille.
     */
    public function create(array $data, User $authUser): User
    {
        $estTuteur = (int) ($data['role_id'] ?? 0) === RoleEnum::Tuteur->value;

        if ($estTuteur) {
            $motDePasse                   = $this->motDePasseRemisEnMainPropre();
            $data['password']             = $motDePasse;
            // Provisoire par nature : il a transite par un tiers, et doit etre
            // remplace des la premiere connexion.
            $data['must_change_password'] = true;
        } else {
            $data = array_merge($data, $this->identifiantsDeDepart());
        }

        $user = $this->userRepository->create($data);

        if ($estTuteur) {
            // Porte sur l'instance et non en base : l'appelant l'affiche une
            // fois a l'agent, puis il disparait. Seule son empreinte subsiste.
            $user->motDePasseProvisoire = $motDePasse;

            return $user;
        }

        $this->reinitialisation->envoyerLienActivation($user);

        return $user;
    }

    /**
     * Un mot de passe provisoire lisible a voix haute.
     *
     * Il sera dicte ou recopie sur un papier : les caracteres qui se
     * confondent a l'oral ou a l'ecrit (O et 0, I, l et 1) en sont exclus, sans
     * quoi la famille se heurterait a un refus de connexion sans comprendre
     * pourquoi.
     */
    private function motDePasseRemisEnMainPropre(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $motDePasse = '';

        for ($i = 0; $i < 10; $i++) {
            $motDePasse .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $motDePasse;
    }

    public function update(int|string $id, array $data, User $authUser): User
    {
        $user = $this->userRepository->findById($id);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function disable(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findById($id);

        if ($user->id === $authUser->id) {
            abort(422, 'Vous ne pouvez pas vous désactiver vous-même.');
        }

        $this->userRepository->update($user, ['deleted_at' => now()]);

        return $user;
    }

    public function toggleStatus(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findById($id);

        if ($user->id === $authUser->id) {
            abort(422, 'Vous ne pouvez pas modifier votre propre statut.');
        }

        return $this->userRepository->update($user, [
            'status' => !$user->status,
        ]);
    }

    public function restore(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findTrashedById($id);

        $this->userRepository->restore($user);

        return $user->fresh('role');
    }

    public function forceDelete(int|string $id): void
    {
        $this->userRepository->forceDelete($id);
    }
}
