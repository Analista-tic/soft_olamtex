<?php

namespace App\Support\Notifications;

/**
 * Catálogo de eventos del sistema que pueden avisar a alguien.
 *
 * El código dispara el evento (NotificationDispatcher::dispatch) y solo la configuración decide
 * cuándo (activo), cómo (campana / correo) y a quién. Los valores de aquí son solo los iniciales:
 * se copian a la BD la primera vez y desde entonces manda lo que configure el superadmin.
 *
 * Destinatarios automáticos ("dynamic"): personas que solo se conocen al ocurrir el evento
 * (el solicitante, quien creó la factura...). Cada evento declara cuáles ofrece.
 * 'required': destinatarios automáticos que el flujo necesita para funcionar (p. ej. quienes deben
 * aprobar); no se pueden quitar de la configuración, solo se les puede añadir gente.
 */
class NotificationEventRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $role = 'role';
        $dynamic = 'dynamic';

        return [
            // ── Compras ─────────────────────────────────────────────────────────────
            'purchase_request_submitted' => [
                'module' => 'Compras',
                'name' => 'Solicitud de compra enviada a aprobación',
                'description' => 'Cuando una solicitud pasa de borrador a pendiente de aprobación.',
                'dynamic' => ['request_target_role' => 'Quienes aprueban en el área a la que se dirige la solicitud (gerencia o logística)'],
                'required' => ['request_target_role'],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$dynamic, 'request_target_role', 'aprobar solicitudes compra']],
                ],
            ],
            'purchase_request_responded' => [
                'module' => 'Compras',
                'name' => 'Solicitud de compra aprobada, rechazada o cancelada',
                'description' => 'Cuando alguien responde una solicitud de compra.',
                'dynamic' => ['requester' => 'Quien hizo la solicitud', 'buyer' => 'Encargado de la compra'],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$dynamic, 'requester', null], [$dynamic, 'buyer', null]],
                ],
            ],
            'purchase_order_created' => [
                'module' => 'Compras',
                'name' => 'Orden de compra creada',
                'description' => 'Cuando se crea una orden de compra (informa para que se genere la factura).',
                'dynamic' => [],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$role, 'contabilidad', null]],
                ],
            ],
            'purchase_order_pending_approval' => [
                'module' => 'Compras',
                'name' => 'Orden de compra pendiente de aprobación',
                'description' => 'Cuando se crea una orden que debe aprobarse antes de facturarse.',
                'dynamic' => [],
                'defaults' => [
                    'enabled' => false, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$role, 'gerencia', 'aprobar orden compra'], [$role, 'admin', 'aprobar orden compra']],
                ],
            ],
            'invoice_difference' => [
                'module' => 'Compras',
                'name' => 'Diferencia en factura pendiente de aprobación',
                'description' => 'Cuando una factura queda con una diferencia (precio, cantidad o recepción de tela) que debe aprobar otra persona.',
                'dynamic' => [],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$role, 'admin', null], [$role, 'contabilidad', null]],
                ],
            ],
            'invoice_difference_approved' => [
                'module' => 'Compras',
                'name' => 'Diferencia de factura aprobada',
                'description' => 'Cuando se aprueba la diferencia de una factura.',
                'dynamic' => ['creator' => 'Quien creó la factura'],
                'defaults' => [
                    'enabled' => false, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$dynamic, 'creator', null]],
                ],
            ],
            'invoice_difference_rejected' => [
                'module' => 'Compras',
                'name' => 'Diferencia de factura rechazada',
                'description' => 'Cuando se rechaza la diferencia de una factura (hay que gestionar la nota crédito).',
                'dynamic' => ['creator' => 'Quien creó la factura'],
                'defaults' => [
                    'enabled' => true, 'database' => false, 'mail' => true, 'exclude_actor' => false,
                    'recipients' => [[$dynamic, 'creator', null]],
                ],
            ],
            'purchase_invoice_rejected' => [
                'module' => 'Compras',
                'name' => 'Factura de compra rechazada',
                'description' => 'Cuando se rechaza una factura de compra.',
                'dynamic' => ['creator' => 'Quien creó la factura'],
                'defaults' => [
                    'enabled' => false, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$dynamic, 'creator', null], [$role, 'contabilidad', null]],
                ],
            ],
            'fabric_reception_completed' => [
                'module' => 'Textil',
                'name' => 'Recepción de tela completa',
                'description' => 'Cuando se marca la recepción completa de una factura de tela (el inventario ya se cargó).',
                'dynamic' => ['creator' => 'Quien creó la factura'],
                'defaults' => [
                    'enabled' => false, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$dynamic, 'creator', null], [$role, 'contabilidad', null]],
                ],
            ],
            // ── Inventario ──────────────────────────────────────────────────────────
            'inventory_request_created' => [
                'module' => 'Inventario',
                'name' => 'Solicitud de inventario o de insumos creada',
                'description' => 'Cuando se crea una solicitud de inventario o de insumos de producción.',
                'dynamic' => [],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => true,
                    'recipients' => [[$role, 'logistica', null]],
                ],
            ],
            // ── Producción ──────────────────────────────────────────────────────────
            'batch_control_novelty' => [
                'module' => 'Producción',
                'name' => 'Novedad en el control de lote',
                'description' => 'Cuando se reporta una novedad al pasar un lote de un proceso a otro.',
                'dynamic' => ['novelty_areas' => 'Áreas responsables del proceso donde ocurrió la novedad, más Desarrollo Organizacional'],
                'required' => ['novelty_areas'],
                'defaults' => [
                    'enabled' => true, 'database' => true, 'mail' => false, 'exclude_actor' => false,
                    'recipients' => [[$dynamic, 'novelty_areas', null]],
                ],
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
