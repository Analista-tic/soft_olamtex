<?php

namespace App\Services\Settings;

use App\Models\Settings\NotificationEvent;
use App\Models\Settings\NotificationEventRecipient;
use App\Models\Settings\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Punto único para avisar: el código dice QUÉ pasó y da el contexto; la configuración
 * (NotificationRuleService) decide si se avisa, por qué canales y a quién.
 *
 * Contexto admitido:
 *  - 'actor'   User|int|null  quien ejecutó la acción (se excluye si el evento lo pide)
 *  - '<clave>' valor de un destinatario automático del evento: User, lista de Users,
 *              nombre de rol (string) o lista de ids de áreas (para novelty_areas)
 */
class NotificationDispatcher
{
    public function __construct(protected NotificationRuleService $rules) {}

    /** @param array<string, mixed> $context */
    public function dispatch(string $eventKey, Notification $notification, array $context = []): void
    {
        try {
            $event = $this->rules->eventFor($eventKey);
            if (! $event || ! $event->enabled) {
                return;
            }

            $channels = array_values(array_filter([
                $event->channel_database ? 'database' : null,
                $event->channel_mail ? 'mail' : null,
            ]));
            if ($channels === []) {
                return;
            }

            $recipients = $this->resolveRecipients($event, $context);
            if ($event->channel_mail && ! $event->channel_database) {
                $recipients = $recipients->filter(fn (User $u) => filled($u->email))->values();
            }
            if ($recipients->isEmpty()) {
                return;
            }

            if (method_exists($notification, 'withChannels')) {
                $notification->withChannels($channels);
            }

            NotificationFacade::send($recipients, $notification);
        } catch (\Throwable $e) {
            // Un aviso fallido nunca debe romper la operación de negocio que lo originó.
            Log::warning('NotificationDispatcher: no se pudo enviar el aviso', ['event' => $eventKey, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, User>
     */
    public function resolveRecipients(NotificationEvent $event, array $context = []): Collection
    {
        $recipients = collect();

        foreach ($event->recipients as $rule) {
            $users = $this->usersFor($rule, $context);

            if (filled($rule->permission_filter)) {
                $users = $users->filter(fn (User $u) => $u->can($rule->permission_filter));
            }

            $recipients = $recipients->merge($users);
        }

        if ($event->exclude_actor) {
            $actorId = $this->actorId($context['actor'] ?? null);
            if ($actorId !== null) {
                $recipients = $recipients->reject(fn (User $u) => $u->id === $actorId);
            }
        }

        return $recipients->unique('id')->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, User>
     */
    private function usersFor(NotificationEventRecipient $rule, array $context): Collection
    {
        switch ($rule->recipient_type) {
            case NotificationEventRecipient::TYPE_ROLE:
                return $this->usersWithRole($rule->recipient_value);

            case NotificationEventRecipient::TYPE_AREA:
                return User::where('area_id', (int) $rule->recipient_value)->get();

            case NotificationEventRecipient::TYPE_USER:
                return User::whereKey((int) $rule->recipient_value)->get();

            case NotificationEventRecipient::TYPE_DYNAMIC:
                return $this->dynamicUsers($context[$rule->recipient_value] ?? null, $rule->recipient_value);
        }

        return collect();
    }

    private function usersWithRole(string $role): Collection
    {
        try {
            return User::role($role)->get();
        } catch (\Throwable $e) {
            Log::warning('NotificationDispatcher: rol no encontrado', ['role' => $role]);

            return collect();
        }
    }

    private function dynamicUsers(mixed $value, string $key): Collection
    {
        if ($value === null) {
            return collect();
        }
        if ($value instanceof User) {
            return collect([$value]);
        }
        if (is_string($value)) {
            return $this->usersWithRole($value);
        }
        if ($key === 'novelty_areas' && is_iterable($value)) {
            return User::whereIn('area_id', collect($value)->all())->get();
        }
        if (is_iterable($value)) {
            return collect($value)->filter(fn ($u) => $u instanceof User)->values();
        }

        return collect();
    }

    private function actorId(mixed $actor): ?int
    {
        if ($actor instanceof User) {
            return $actor->id;
        }

        return is_numeric($actor) ? (int) $actor : null;
    }
}
