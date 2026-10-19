<?php

namespace App\Services\Settings;

use App\Models\Settings\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Obtiene las notificaciones paginadas del usuario con filtros.
     *
     * @param  array{
     *      search?: string|null,
     *      status?: string|null,
     *      area?: string|null,
     *      type?: string|null,
     *      date_from?: string|null,
     *      date_to?: string|null
     * }  $filters
     */
    public function paginate(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = $this->normalizeFilter($filters, 'search');
        $status = $this->normalizeFilter($filters, 'status');
        $area = $this->normalizeFilter($filters, 'area');
        $type = $this->normalizeFilter($filters, 'type');
        $dateFrom = $this->normalizeFilter($filters, 'date_from');
        $dateTo = $this->normalizeFilter($filters, 'date_to');
        $showArchived = $this->normalizeFilter($filters, 'show_archived') === '1';

        $query = $user->notifications()
            ->whereNull('deleted_at')
            ->when(!$showArchived, fn (Builder $q) => $q->whereNull('archived_at'))
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('data->title', 'like', "%{$search}%")
                      ->orWhere('data->body', 'like', "%{$search}%");
                });
            })
            ->when($status === 'read', fn (Builder $q) => $q->whereNotNull('read_at'))
            ->when($status === 'unread', fn (Builder $q) => $q->whereNull('read_at'))
            ->when($area, fn (Builder $q) => $q->where('data->area', $area))
            ->when($type, fn (Builder $q) => $q->where('data->type', $type))
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('created_at', '>=', Carbon::parse($dateFrom)))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('created_at', '<=', Carbon::parse($dateTo)))
            ->latest();

        $paginated = $query->paginate($perPage);

        // Transformar las notificaciones al formato esperado por el frontend
        $paginated->getCollection()->transform(fn ($notification) => $this->transform($notification));

        return $paginated;
    }

    /**
     * Obtiene las últimas N notificaciones del usuario (para el dropdown de la campana).
     * Excluye archivadas y eliminadas por defecto.
     */
    public function latest(User $user, int $limit = 10): Collection
    {
        return $user->notifications()
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($notification) => $this->transform($notification));
    }

    /**
     * Obtiene el conteo de notificaciones no leídas del usuario.
     * Excluye archivadas y eliminadas.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->count();
    }

    /**
     * Marca una notificación como leída.
     */
    public function markAsRead(User $user, string $notificationId): ?DatabaseNotification
    {
        $notification = $user->notifications()->find($notificationId);

        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
        }

        return $notification;
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Obtiene los tipos de notificaciones únicos del usuario (para el filtro de tipo).
     */
    public function getAvailableTypes(User $user): Collection
    {
        $expr = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.type'))";

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereRaw("JSON_EXTRACT(data, '$.type') IS NOT NULL")
            ->selectRaw("{$expr} as type")
            ->distinct()
            ->orderByRaw("{$expr} ASC")
            ->pluck('type')
            ->filter()
            ->values();
    }

    /**
     * Transforma una notificación al formato esperado por el frontend.
     *
     * @return array{
     *      id: string,
     *      type: string|null,
     *      title: string|null,
     *      body: string|null,
     *      module: string|null,
     *      url: string|null,
     *      data: array<string, mixed>,
     *      area: string|null,
     *      user_name: string|null,
     *      entity_id: int|string|null,
     *      read_at: string|null,
     *      archived_at: string|null,
     *      created_at: string,
     *      is_read: bool
     * }
     */
    protected function transform(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];
        $archivedAt = $notification->archived_at;

        // Notificaciones de solicitudes de compra anteriores al deep link: se completa la URL
        // con ?ver=<id> para que abran el dialog de la solicitud.
        $url = $data['url'] ?? null;
        if ($url && !empty($data['request_id']) && str_starts_with((string) ($data['type'] ?? ''), 'purchase_request_') && !str_contains($url, 'ver=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'ver=' . $data['request_id'];
        }

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? $notification->type ?? null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'module' => $data['module'] ?? null,
            'url' => $url,
            'data' => $data,
            'area' => $data['area'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            // Campos específicos para novedades de control de lotes
            'op_code' => $data['op_code'] ?? null,
            'from_process' => $data['from_process'] ?? null,
            'to_process' => $data['to_process'] ?? null,
            'reporter' => $data['reporter'] ?? null,
            'reported_at' => $data['reported_at'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'archived_at' => $archivedAt ? Carbon::parse($archivedAt)->toIso8601String() : null,
            'created_at' => $notification->created_at->toIso8601String(),
            'is_read' => $notification->read_at !== null,
        ];
    }

    /**
     * Archiva una notificación del usuario.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function archiveForUser(User $user, string $notificationId): void
    {
        $notification = $user->notifications()
            ->whereNull('deleted_at')
            ->findOrFail($notificationId);

        $notification->update(['archived_at' => now()]);
    }

    /**
     * Elimina (soft) una notificación del usuario.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteForUser(User $user, string $notificationId): void
    {
        $notification = $user->notifications()
            ->whereNull('deleted_at')
            ->findOrFail($notificationId);

        $notification->update(['deleted_at' => now()]);
    }

    /**
     * Normaliza un valor de filtro.
     */
    protected function normalizeFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_string($value) ? trim($value) : (string) $value;
    }
}
