<?php

namespace App\Services\MasterData;

use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\Settings\SettingService;

class ParameterUsageService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const SYSTEM_USAGE_BY_KEY = [
        'areas' => [
            'Define las areas disponibles para usuarios, auditoria y solicitudes internas.',
        ],
        'sections' => [
            'Se usa como catalogo de secciones en fichas de producto, batch control y operaciones.',
        ],
        'groups' => [
            'Se usa como catalogo de grupos en fichas de producto y catalogos operativos.',
        ],
        'brands' => [
            'Se usa como catalogo de marcas en fichas de producto.',
        ],
        'worlds' => [
            'Se usa como catalogo de mundos en fichas de producto.',
        ],
        // fabric_bases / yarn_bases se retiraron: sus catálogos viven en las tablas
        // dedicadas fabric_bases / yarn_bases, no en settings. Mantenerlos aquí hacía
        // que el sistema declarase "en uso" unas filas que ya nadie leía, impidiendo
        // borrarlas desde la pantalla de Parámetros.
        'sizes' => [
            'Se usa como catalogo de tallas en fichas de producto y batch control.',
        ],
        'operational_lists' => [
            'Se usa en la asignacion de listados operacionales.',
        ],
        'process_types' => [
            'Se usa en la configuracion de procesos de ficha tecnica.',
        ],
        'unit_measures' => [
            'Se usa en unidades de medida para insumos y formularios de produccion.',
        ],
        'thread_brands' => [
            'Se usa en catalogos de marcas de hilo.',
        ],
        'preparation_groups' => [
            'Se usa para agrupar operaciones de preparacion.',
        ],
        'credit_note_reason' => [
            'Se usa para validar los motivos permitidos de notas credito.',
        ],
        'debit_note_reason' => [
            'Se usa para validar los motivos permitidos de notas debito.',
        ],
        'operation_cost' => [
            'Se usa en el calculo de costos de listados operacionales.',
        ],
        'yarn_cost' => [
            'Se usa en el calculo del costo total del producto.',
        ],
    ];

    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(Setting $parameter): array
    {
        $usages = [
            ...$this->resolveSystemUsages($parameter),
            ...$this->resolveDataUsages($parameter),
        ];

        $canDelete = $usages === [];

        return [
            'can_delete' => $canDelete,
            'title' => $canDelete
                ? 'Confirmar eliminación'
                : 'No se puede eliminar el parámetro',
            'message' => $canDelete
                ? '¿Deseas eliminar este parámetro? Esta acción no se puede deshacer.'
                : 'Este parámetro está siendo utilizado en otros componentes o registros del sistema. Para conservar la integridad de la información, solo puedes desactivarlo.',
            'suggested_action' => $canDelete ? null : 'deactivate',
            'parameter' => [
                'id' => (int) $parameter->id,
                'key' => (string) $parameter->key,
                'label' => (string) $parameter->label,
            ],
            'usages' => $usages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveSystemUsages(Setting $parameter): array
    {
        $canonicalKey = $this->settingService->canonicalKey((string) $parameter->key);
        $details = self::SYSTEM_USAGE_BY_KEY[$canonicalKey] ?? [];

        return array_map(
            static fn (string $detail): array => [
                'type' => 'system',
                'label' => 'Uso del sistema',
                'detail' => $detail,
            ],
            $details,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveDataUsages(Setting $parameter): array
    {
        $canonicalKey = $this->settingService->canonicalKey((string) $parameter->key);
        $usages = [];

        if ($canonicalKey === 'areas') {
            $assignedUsers = User::query()
                ->where('area_id', $parameter->id)
                ->count();

            if ($assignedUsers > 0) {
                $usages[] = [
                    'type' => 'record',
                    'label' => 'Usuarios',
                    'detail' => $assignedUsers === 1
                        ? '1 usuario tiene esta area asignada.'
                        : "{$assignedUsers} usuarios tienen esta area asignada.",
                    'count' => $assignedUsers,
                ];
            }
        }

        return $usages;
    }
}
