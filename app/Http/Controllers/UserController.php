<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\UserServiceInterface;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Le personnel dont un manager peut reinitialiser les acces.
     *
     * Volontairement sans 'admin' ni 'manager' : un role ne doit pas pouvoir
     * se donner la main sur un compte de rang egal ou superieur. L'admin, lui,
     * n'est pas soumis a cette liste.
     */
    private const ROLES_REINITIALISABLES_PAR_MANAGER = ['treasurer', 'supervisor', 'teacher'];

    public function __construct(
        private readonly UserServiceInterface          $userService,
        private readonly PasswordResetServiceInterface $reinitialisation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $users = $this->userService->list(
            perPage:    (int) $request->input('per_page', 10),
            search:     trim($request->input('search', '')),
            searchRole: trim($request->input('searchRole', '')),
        );

        return ApiResponse::paginated($users, 'Liste des utilisateurs récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $user = $this->userService->find($id);

        return ApiResponse::success($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated(), $request->user());

        // Le tuteur n'a pas d'accès par courriel : son mot de passe provisoire
        // remonte ici pour être lu à l'agent qui le transmettra. Il n'est
        // renvoyé qu'à cet instant, et n'est stocké nulle part en clair.
        if (isset($user->motDePasseProvisoire)) {
            return ApiResponse::success([
                'user'                  => $user,
                'mot_de_passe_provisoire' => $user->motDePasseProvisoire,
            ], 'Compte créé. Communiquez ce mot de passe provisoire à son titulaire.', 201);
        }

        return ApiResponse::success(
            $user,
            'Utilisateur créé. Un lien d\'activation vient de lui être envoyé par e-mail.',
            201
        );
    }

    /**
     * Renvoie le lien d'ouverture d'un compte.
     *
     * Pour les messages perdus, tombes dans les indesirables, ou dont le lien a
     * expire avant d'avoir servi. Emettre un nouveau lien annule le precedent :
     * la table des jetons a l'adresse pour cle, un compte n'a jamais qu'un lien
     * vivant a la fois.
     *
     * Reserve a l'administration : c'est elle qui ouvre les comptes, et le
     * titulaire — qui n'a pas encore d'acces — ne peut pas le demander lui-meme.
     * S'il a deja active son compte, « mot de passe oublie » est la bonne porte.
     */
    public function renvoyerActivation(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $user = $this->userService->find($id);

        if ($user->email_verified_at) {
            return ApiResponse::error(
                'Ce compte est déjà activé. Son titulaire peut utiliser « mot de passe oublié ».',
                422
            );
        }

        $issue = $this->reinitialisation->envoyerLienActivation($user);

        if ($issue === PasswordResetService::ACTIVATION_IMPOSSIBLE) {
            return ApiResponse::error(
                'Ce compte n\'a pas d\'adresse e-mail valide, ou il est désactivé.',
                422
            );
        }

        return ApiResponse::success(null, 'Le lien d\'activation a été renvoyé.');
    }

    /**
     * Reinitialise les acces d'un membre du personnel.
     *
     * Envoie a l'interesse le lien qui lui permet de choisir un nouveau mot de
     * passe. L'administration ne le connait donc jamais : elle ouvre la porte,
     * elle n'entre pas. L'ancien mot de passe reste valable tant que le lien
     * n'a pas servi — un clic par erreur ne coupe l'acces de personne.
     *
     * Le manager n'atteint que le personnel qu'il encadre. Un compte admin, ou
     * un autre manager, lui reste ferme : sans quoi le role se donnerait a
     * lui-meme les acces de plus haut que lui.
     *
     * Un compte jamais active n'a pas de mot de passe a reinitialiser : c'est
     * alors le lien d'ouverture de compte qui repart, silencieusement, plutot
     * que de renvoyer l'appelant vers un second endpoint pour la meme intention.
     */
    public function reinitialiserAcces(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $auteur = $request->user();
        $user   = $this->userService->find($id);

        if ($auteur->id === $user->id) {
            return ApiResponse::error(
                'Pour votre propre compte, utilisez « changer mon mot de passe ».',
                422
            );
        }

        if ($auteur->role?->name === 'manager'
            && ! in_array($user->role?->name, self::ROLES_REINITIALISABLES_PAR_MANAGER, true)) {
            return ApiResponse::error(
                'Vous ne pouvez réinitialiser que les accès du personnel que vous encadrez.',
                403
            );
        }

        if (! $user->email_verified_at) {
            $issue = $this->reinitialisation->envoyerLienActivation($user);

            if ($issue === PasswordResetService::ACTIVATION_IMPOSSIBLE) {
                return ApiResponse::error(
                    'Ce compte n\'a pas d\'adresse e-mail valide, ou il est désactivé.',
                    422
                );
            }

            return ApiResponse::success(
                null,
                'Ce compte n\'avait jamais été activé : son lien d\'activation vient d\'être envoyé à ' . $user->email . '.'
            );
        }

        $issue = $this->reinitialisation->envoyerLienAdministratif($user, $auteur);

        if ($issue === PasswordResetService::REINIT_ADMIN_SANS_EMAIL) {
            return ApiResponse::error(
                'Ce compte n\'a pas d\'adresse e-mail : impossible de lui envoyer un lien.',
                422
            );
        }

        if ($issue === PasswordResetService::REINIT_ADMIN_INACTIF) {
            return ApiResponse::error(
                'Ce compte est désactivé. Réactivez-le avant de réinitialiser ses accès.',
                422
            );
        }

        return ApiResponse::success(
            null,
            'Un lien de réinitialisation vient d\'être envoyé à ' . $user->email . '.'
        );
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated(), $request->user());

        return ApiResponse::success($user, 'Utilisateur modifié avec succès');
    }

    public function disable(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $user = $this->userService->disable($id, $request->user());

        return ApiResponse::success($user, 'Utilisateur désactivé avec succès');
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $user = $this->userService->toggleStatus($id, $request->user());

        $message = $user->status
            ? 'Utilisateur activé avec succès.'
            : 'Utilisateur désactivé avec succès.';

        return ApiResponse::success($user, $message);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $user = $this->userService->restore($id, $request->user());

        return ApiResponse::success($user, 'Utilisateur réactivé avec succès.');
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $this->userService->forceDelete($id);

        return ApiResponse::success(null, 'Utilisateur supprimé définitivement.');
    }

    private function authorizeRoles(Request $request, array $roles): void
    {
        if (!in_array($request->user()?->role?->name, $roles)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
