<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ContratServiceInterface;
use App\Interfaces\EnseignantRepositoryInterface;
use App\Interfaces\EnseignantServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Enseignant;
use App\Models\User;
use App\Services\Concerns\OuvreLeCompte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EnseignantService implements EnseignantServiceInterface
{
    use OuvreLeCompte;

    public function __construct(
        private readonly EnseignantRepositoryInterface $repository,
        private readonly UserRepositoryInterface       $userRepository,
        private readonly ActivityLogServiceInterface   $activityLog,
        private readonly ContratServiceInterface       $contratService,
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

    public function find(int|string $id): Enseignant
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Enseignant
    {
        $enseignant = DB::transaction(function () use ($data, $authUser) {
            $userData = array_merge(
                $data['user'],
                ['role_id' => RoleEnum::Teacher->value],
                $this->identifiantsDeDepart(),
            );

            $user   = $this->userRepository->create($userData);
            $profil = $data['profil'] ?? [];

            if (empty($profil['matricule'])) {
                $profil['matricule'] = $this->repository->nextMatricule();
            }

            $enseignant = $this->repository->create(
                array_merge($profil, ['user_id' => $user->id])
            );

            // Un enseignant n'existe pas sans engagement : son contrat naît avec
            // lui, dans la même transaction, à partir des conditions saisies.
            $this->contratService->creerPourNouveauProfil(
                contractable: $enseignant,
                donnees:      $data['contrat'] ?? [],
                authUser:     $authUser,
            );

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'enseignants',
                description: "Création de l'enseignant « {$user->first_name} {$user->last_name} » (matricule : {$enseignant->matricule})",
                subject:     $enseignant,
                newValues:   $enseignant->toArray(),
            );

            return $enseignant->load('contratActif', 'user');
        });

        // Hors transaction : un courriel ne se rattrape pas si celle-ci est
        // annulee ensuite, et l'enseignant recevrait un lien vers un compte
        // qui n'existe pas.
        $this->reinitialisation->envoyerLienActivation($enseignant->user);

        return $enseignant;
    }

    public function update(int|string $id, array $data, User $authUser): Enseignant
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $enseignant = $this->repository->findById($id);
            $oldValues  = $enseignant->toArray();

            if (!empty($data['user'])) {
                $this->userRepository->update($enseignant->user, $data['user']);
            }

            $enseignant = $this->repository->update($enseignant, $data['profil'] ?? []);

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'enseignants',
                description: "Modification de l'enseignant « {$enseignant->user?->first_name} {$enseignant->user?->last_name} » (matricule : {$enseignant->matricule})",
                subject:     $enseignant,
                oldValues:   $oldValues,
                newValues:   $enseignant->toArray(),
            );

            return $enseignant;
        });
    }

    public function delete(int|string $id, User $authUser): void
    {
        $enseignant = $this->repository->findById($id);

        $this->repository->delete($enseignant);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'enseignants',
            description: "Suppression de l'enseignant « {$enseignant->user?->first_name} {$enseignant->user?->last_name} » (matricule : {$enseignant->matricule})",
            subject:     $enseignant,
            oldValues:   $enseignant->toArray(),
        );
    }

    public function restore(int|string $id, User $authUser): Enseignant
    {
        $enseignant = $this->repository->findTrashedById($id);

        $this->repository->restore($enseignant);

        $enseignant = $enseignant->fresh('user.role');

        $this->activityLog->log(
            user:        $authUser,
            action:      'restored',
            module:      'enseignants',
            description: "Restauration de l'enseignant « {$enseignant->user?->first_name} {$enseignant->user?->last_name} » (matricule : {$enseignant->matricule})",
            subject:     $enseignant,
        );

        return $enseignant;
    }
}
