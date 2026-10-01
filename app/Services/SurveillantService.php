<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ContratServiceInterface;
use App\Interfaces\SurveillantRepositoryInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\SurveillantServiceInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Surveillant;
use App\Models\User;
use App\Services\Concerns\OuvreLeCompte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SurveillantService implements SurveillantServiceInterface
{
    use OuvreLeCompte;

    public function __construct(
        private readonly SurveillantRepositoryInterface $repository,
        private readonly UserRepositoryInterface        $userRepository,
        private readonly ActivityLogServiceInterface    $activityLog,
        private readonly ContratServiceInterface        $contratService,
        private readonly PasswordResetServiceInterface  $reinitialisation,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Surveillant
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Surveillant
    {
        $surveillant = DB::transaction(function () use ($data, $authUser) {
            $userData = array_merge(
                $data['user'],
                ['role_id' => RoleEnum::Supervisor->value],
                $this->identifiantsDeDepart(),
            );

            $user   = $this->userRepository->create($userData);
            $profil = $data['profil'] ?? [];

            if (empty($profil['matricule'])) {
                $profil['matricule'] = $this->repository->nextMatricule();
            }

            $surveillant = $this->repository->create(
                array_merge($profil, ['user_id' => $user->id])
            );

            // Un surveillant n'existe pas sans engagement : son contrat naît avec
            // lui, dans la même transaction, à partir des conditions saisies.
            $this->contratService->creerPourNouveauProfil(
                contractable: $surveillant,
                donnees:      $data['contrat'] ?? [],
                authUser:     $authUser,
            );

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'surveillants',
                description: "Création du surveillant « {$user->first_name} {$user->last_name} » (matricule : {$surveillant->matricule})",
                subject:     $surveillant,
                newValues:   $surveillant->toArray(),
            );

            return $surveillant->load('contratActif', 'user');
        });

        // Hors transaction : un courriel ne se rattrape pas si celle-ci est
        // annulee ensuite, et le surveillant recevrait un lien vers un compte
        // qui n'existe pas.
        $this->reinitialisation->envoyerLienActivation($surveillant->user);

        return $surveillant;
    }

    public function update(int|string $id, array $data, User $authUser): Surveillant
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $surveillant = $this->repository->findById($id);
            $oldValues   = $surveillant->toArray();

            if (!empty($data['user'])) {
                $this->userRepository->update($surveillant->user, $data['user']);
            }

            $surveillant = $this->repository->update($surveillant, $data['profil'] ?? []);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'surveillants',
                description: "Modification du surveillant « {$surveillant->user?->first_name} {$surveillant->user?->last_name} » (matricule : {$surveillant->matricule})",
                subject:     $surveillant,
                oldValues:   $oldValues,
                newValues:   $surveillant->toArray(),
            );

            return $surveillant;
        });
    }

    public function delete(int|string $id, User $authUser): void
    {
        $surveillant = $this->repository->findById($id);

        $this->repository->delete($surveillant);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'surveillants',
            description: "Suppression du surveillant « {$surveillant->user?->first_name} {$surveillant->user?->last_name} » (matricule : {$surveillant->matricule})",
            subject:     $surveillant,
            oldValues:   $surveillant->toArray(),
        );
    }

    public function restore(int|string $id, User $authUser): Surveillant
    {
        $surveillant = $this->repository->findTrashedById($id);

        $this->repository->restore($surveillant);

        $surveillant = $surveillant->fresh('user.role');

        $this->activityLog->log(
            user:        $authUser,
            action:      'restored',
            module:      'surveillants',
            description: "Restauration du surveillant « {$surveillant->user?->first_name} {$surveillant->user?->last_name} » (matricule : {$surveillant->matricule})",
            subject:     $surveillant,
        );

        return $surveillant;
    }
}
