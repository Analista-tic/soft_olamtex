<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function __construct(protected NotificationService $service) {}

    /**
     * Muestra la página principal de notificaciones (Inertia).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->extractFilters($request);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $notifications = $this->service->paginate($user, $filters, $perPage);

        return Inertia::render('Configuration/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $filters,
        ]);
    }

    /**
     * Endpoint JSON para obtener datos de la tabla (paginados).
     */
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $this->extractFilters($request);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $notifications = $this->service->paginate($user, $filters, $perPage);

        return response()->json([
            'data' => $notifications->items(),
            'total' => $notifications->total(),
            'per_page' => $notifications->perPage(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ]);
    }

    /**
     * Obtiene las últimas notificaciones para el dropdown de la campana.
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min((int) $request->input('limit', 10), 20));

        $notifications = $this->service->latest($user, $limit);

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * Obtiene el conteo de notificaciones no leídas.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->service->unreadCount($user);

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $this->service->markAsRead($user, $id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notificación no encontrada.',
            ], 404);
        }

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'notification' => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->service->markAllAsRead($user);

        return response()->json([
            'message' => "{$count} notificaciones marcadas como leídas.",
            'marked_count' => $count,
        ]);
    }

    /**
     * Obtiene los tipos de notificaciones disponibles (para el filtro).
     */
    public function types(Request $request): JsonResponse
    {
        $user = $request->user();
        $types = $this->service->getAvailableTypes($user);

        return response()->json([
            'data' => $types,
        ]);
    }

    /**
     * Archiva una notificación del usuario.
     */
    public function archive(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        try {
            $this->service->archiveForUser($user, $id);

            return response()->json(null, 204);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'message' => 'Notificación no encontrada.',
            ], 404);
        }
    }

    /**
     * Elimina (soft delete) una notificación del usuario.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        try {
            $this->service->deleteForUser($user, $id);

            return response()->json(null, 204);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'message' => 'Notificación no encontrada.',
            ], 404);
        }
    }

    /**
     * Extrae los filtros válidos del request.
     *
     * @return array{
     *      search?: string|null,
     *      status?: string|null,
     *      area?: string|null,
     *      type?: string|null,
     *      date_from?: string|null,
     *      date_to?: string|null,
     *      show_archived?: string|null
     * }
     */
    protected function extractFilters(Request $request): array
    {
        $filters = $request->only(['search', 'status', 'area', 'type', 'date_from', 'date_to', 'show_archived']);

        foreach ($filters as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $filters[$key] = null;
            }
        }

        return $filters;
    }
}
