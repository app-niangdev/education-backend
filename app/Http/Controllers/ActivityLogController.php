<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Interfaces\ActivityLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// activelog
class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogServiceInterface $activityLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $filters = [
            'search'    => trim($request->input('search', '')),
            'user_id'   => $request->input('user_id'),
            'action'    => $request->input('action'),
            'module'    => trim($request->input('module', '')),
            'date_from' => $request->input('date_from'),
            'date_to'   => $request->input('date_to'),
        ];

        $logs = $this->activityLogService->list(
            perPage: (int) $request->input('per_page', 20),
            filters: $filters,
        );

        return ApiResponse::paginated($logs, 'Journal d\'activité récupéré avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $log = $this->activityLogService->find($id);

        return ApiResponse::success($log);
    }

    public function purge(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin']);

        $days    = (int) $request->input('days', 90);
        $deleted = $this->activityLogService->purge($days);

        return ApiResponse::success(
            ['deleted_count' => $deleted],
            "Purge effectuée : {$deleted} entrée(s) supprimée(s) (antérieures à {$days} jours)."
        );
    }

    private function authorizeRoles(Request $request, array $roles): void
    {
        if (!in_array($request->user()?->role?->name, $roles)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
