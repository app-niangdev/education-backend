<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ContratServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\TresorierRepositoryInterface;
use App\Interfaces\TresorierServiceInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Tresorier;
use App\Models\User;
use App\Services\Concerns\OuvreLeCompte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TresorierService implements TresorierServiceInterface
{
    use OuvreLeCompte;

    public function __construct(
        private readonly TresorierRepositoryInterface $repository,
        private readonly UserRepositoryInterface      $userRepository,
        private readonly ActivityLogServiceInterface  $activityLog,
        private readonly ContratServiceInterface      $contratService,
        private readonly PasswordResetServiceInterface $reinitialisation,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Tresorier
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Tresorier
    {
        $tresorier = DB::transaction(function () use ($data, $authUser) {
            $userData = array_merge(
                $data['user'],
                ['role_id' => RoleEnum::Treasurer->value],
                $this->identifiantsDeDepart(),
            );

            $user   = $this->userRepository->create($userData);
            $profil = $data['profil'] ?? [];

            if (empty($profil['matricule'])) {
                $profil['matricule'] = $this->repository->nextMatricule();
            }

            $tresorier = $this->repository->create(
                array_merge($profil, ['user_id' => $user->id])
            );

            // Un trésorier n'existe pas sans engagement : son contrat naît avec
            // lui, dans la même transaction, à partir des conditions saisies.
            $this->contratService->creerPourNouveauProfil(
                contractable: $tresorier,
                donnees:      $data['contrat'] ?? [],
                authUser:     $authUser,
            );

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'tresoriers',
                description: "Création du trésorier « {$user->first_name} {$user->last_name} » (matricule : {$tresorier->matricule})",
                subject:     $tresorier,
                newValues:   $tresorier->toArray(),
            );

            return $tresorier->load('contratActif', 'user');
        });

        // Hors transaction : un courriel ne se rattrape pas si celle-ci est
        // annulee ensuite, et le tresorier recevrait un lien vers un compte
        // qui n'existe pas.
        $this->reinitialisation->envoyerLienActivation($tresorier->user);

        return $tresorier;
    }

    public function update(int|string $id, array $data, User $authUser): Tresorier
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $tresorier = $this->repository->findById($id);
            $oldValues = $tresorier->toArray();

            if (!empty($data['user'])) {
                $this->userRepository->update($tresorier->user, $data['user']);
            }

            $tresorier = $this->repository->update($tresorier, $data['profil'] ?? []);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'tresoriers',
                description: "Modification du trésorier « {$tresorier->user?->first_name} {$tresorier->user?->last_name} » (matricule : {$tresorier->matricule})",
                subject:     $tresorier,
                oldValues:   $oldValues,
                newValues:   $tresorier->toArray(),
            );

            return $tresorier;
        });
    }

    public function delete(int|string $id, User $authUser): void
    {
        $tresorier = $this->repository->findById($id);

        $this->repository->delete($tresorier);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'tresoriers',
            description: "Suppression du trésorier « {$tresorier->user?->first_name} {$tresorier->user?->last_name} » (matricule : {$tresorier->matricule})",
            subject:     $tresorier,
            oldValues:   $tresorier->toArray(),
        );
    }

    public function restore(int|string $id, User $authUser): Tresorier
    {
        $tresorier = $this->repository->findTrashedById($id);

        $this->repository->restore($tresorier);

        $tresorier = $tresorier->fresh('user.role');

        $this->activityLog->log(
            user:        $authUser,
            action:      'restored',
            module:      'tresoriers',
            description: "Restauration du trésorier « {$tresorier->user?->first_name} {$tresorier->user?->last_name} » (matricule : {$tresorier->matricule})",
            subject:     $tresorier,
        );

        return $tresorier;
    }
}
