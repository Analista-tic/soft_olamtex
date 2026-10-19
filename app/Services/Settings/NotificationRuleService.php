<?php

namespace App\Services\Settings;

use App\Models\Settings\Area;
use App\Models\Settings\NotificationEvent;
use App\Models\Settings\NotificationEventRecipient;
use App\Models\Settings\User;
use App\Support\Notifications\NotificationEventRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Lee y guarda la configuración de notificaciones (cuándo, cómo y a quién). */
class NotificationRuleService
{
    /** Crea en BD los eventos del catálogo que aún no existen, con sus valores iniciales. */
    public function ensureDefaults(): void
    {
        $existing = NotificationEvent::query()->pluck('event_key')->all();

        foreach (NotificationEventRegistry::all() as $key => $definition) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            DB::transaction(function () use ($key, $definition) {
                $defaults = $definition['defaults'];
                $event = NotificationEvent::create([
                    'event_key' => $key,
                    'enabled' => $defaults['enabled'],
                    'channel_database' => $defaults['database'],
                    'channel_mail' => $defaults['mail'],
                    'exclude_actor' => $defaults['exclude_actor'],
                ]);

                foreach ($defaults['recipients'] as [$type, $value, $permission]) {
                    $event->recipients()->create([
                        'recipient_type' => $type,
                        'recipient_value' => $value,
                        'permission_filter' => $permission,
                    ]);
                }
            });
        }
    }

    public function eventFor(string $key): ?NotificationEvent
    {
        if (! NotificationEventRegistry::has($key)) {
            return null;
        }

        $this->ensureDefaults();

        return NotificationEvent::with('recipients')->where('event_key', $key)->first();
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $this->ensureDefaults();

        $events = NotificationEvent::with('recipients')->get()->keyBy('event_key');
        $labels = $this->recipientLabels();
        $rows = [];

        foreach (NotificationEventRegistry::all() as $key => $definition) {
            $event = $events->get($key);
            if (! $event) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'module' => $definition['module'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'dynamic_options' => collect($definition['dynamic'])->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values()->all(),
                'enabled' => $event->enabled,
                'channel_database' => $event->channel_database,
                'channel_mail' => $event->channel_mail,
                'exclude_actor' => $event->exclude_actor,
                'recipients' => $event->recipients->map(fn (NotificationEventRecipient $r) => [
                    'type' => $r->recipient_type,
                    'value' => $r->recipient_value,
                    'locked' => $r->recipient_type === NotificationEventRecipient::TYPE_DYNAMIC
                        && in_array($r->recipient_value, $definition['required'] ?? [], true),
                    'label' => $this->labelFor($r, $labels, $definition),
                    'permission_filter' => $r->permission_filter,
                ])->values()->all(),
            ];
        }

        return $rows;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function options(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name')->map(fn ($n) => ['id' => $n, 'name' => $n])->all(),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name'])->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])->all(),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => (string) $u->id, 'name' => $u->name])->all(),
            'permissions' => Permission::query()->orderBy('name')->pluck('name')->map(fn ($n) => ['id' => $n, 'name' => $n])->all(),
        ];
    }

    /**
     * @param  array{enabled: bool, channel_database: bool, channel_mail: bool, exclude_actor: bool, recipients: list<array{type: string, value: string, permission_filter?: string|null}>}  $data
     */
    public function update(string $key, array $data): NotificationEvent
    {
        $definition = NotificationEventRegistry::find($key);
        if (! $definition) {
            throw ValidationException::withMessages(['event' => ['Evento de notificación desconocido.']]);
        }

        $this->ensureDefaults();
        $this->validateRecipients($data['recipients'], array_keys($definition['dynamic']));
        $this->ensureRequiredRecipients($data['recipients'], $definition['required'] ?? [], $definition['dynamic']);

        return DB::transaction(function () use ($key, $data) {
            $event = NotificationEvent::where('event_key', $key)->firstOrFail();
            $event->update([
                'enabled' => $data['enabled'],
                'channel_database' => $data['channel_database'],
                'channel_mail' => $data['channel_mail'],
                'exclude_actor' => $data['exclude_actor'],
            ]);

            $event->recipients()->delete();
            foreach ($data['recipients'] as $recipient) {
                $event->recipients()->create([
                    'recipient_type' => $recipient['type'],
                    'recipient_value' => (string) $recipient['value'],
                    'permission_filter' => $recipient['permission_filter'] ?? null,
                ]);
            }

            return $event->load('recipients');
        });
    }

    /**
     * Los destinatarios automáticos que el flujo necesita no pueden faltar: si se quitaran,
     * el aviso dejaría de llegar a quienes deben enterarse (p. ej. quienes aprueban).
     *
     * @param  list<array{type: string, value: string}>  $recipients
     * @param  list<string>  $required
     * @param  array<string, string>  $dynamicLabels
     */
    private function ensureRequiredRecipients(array $recipients, array $required, array $dynamicLabels): void
    {
        foreach ($required as $key) {
            $present = collect($recipients)->contains(
                fn (array $r) => $r['type'] === NotificationEventRecipient::TYPE_DYNAMIC && (string) $r['value'] === $key
            );
            if (! $present) {
                throw ValidationException::withMessages([
                    'recipients' => ['No se puede quitar «'.($dynamicLabels[$key] ?? $key).'»: el sistema lo necesita para avisar a quien corresponde.'],
                ]);
            }
        }
    }

    /** @param list<string> $allowedDynamic */
    private function validateRecipients(array $recipients, array $allowedDynamic): void
    {
        $roles = Role::pluck('name')->all();
        $areas = Area::pluck('id')->map(fn ($id) => (string) $id)->all();
        $users = User::pluck('id')->map(fn ($id) => (string) $id)->all();
        $permissions = Permission::pluck('name')->all();

        foreach ($recipients as $i => $recipient) {
            $type = $recipient['type'];
            $value = (string) $recipient['value'];
            $valid = match ($type) {
                NotificationEventRecipient::TYPE_ROLE => in_array($value, $roles, true),
                NotificationEventRecipient::TYPE_AREA => in_array($value, $areas, true),
                NotificationEventRecipient::TYPE_USER => in_array($value, $users, true),
                NotificationEventRecipient::TYPE_DYNAMIC => in_array($value, $allowedDynamic, true),
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages(["recipients.$i.value" => ['El destinatario elegido no es válido para este evento.']]);
            }

            $permission = $recipient['permission_filter'] ?? null;
            if ($permission !== null && $permission !== '' && ! in_array($permission, $permissions, true)) {
                throw ValidationException::withMessages(["recipients.$i.permission_filter" => ['El permiso elegido no existe.']]);
            }
        }
    }

    /** @return array{areas: array<string,string>, users: array<string,string>} */
    private function recipientLabels(): array
    {
        return [
            'areas' => Area::pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(),
            'users' => User::pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(),
        ];
    }

    private function labelFor(NotificationEventRecipient $r, array $labels, array $definition): string
    {
        return match ($r->recipient_type) {
            NotificationEventRecipient::TYPE_ROLE => $r->recipient_value,
            NotificationEventRecipient::TYPE_AREA => $labels['areas'][$r->recipient_value] ?? 'Área eliminada',
            NotificationEventRecipient::TYPE_USER => $labels['users'][$r->recipient_value] ?? 'Usuario eliminado',
            default => $definition['dynamic'][$r->recipient_value] ?? $r->recipient_value,
        };
    }
}
