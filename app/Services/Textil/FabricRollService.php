<?php

namespace App\Services\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentItemReception;
use App\Models\Expenses\PurchaseDocumentReceptionEvent;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\Item;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Models\Textil\FabricRollLengthDifference;
use App\Services\Audit\AuditLogService;
use App\Services\Finance\AccountingPostingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseResolver;
use App\Support\MasterData\MaterialClassifier;
use App\Support\Textil\FabricRollBalance;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FabricRollService
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected WarehouseResolver $warehouseResolver,
        protected AccountingPostingService $accountingPostingService
    ) {}

    /** Tolerancia de cantidad al aprobar recepción: ±30 mts respecto al pedido por ítem (valor absoluto, no porcentaje). */
    public const TOLERANCE_QUANTITY_METERS = 30;
    public const TOLERANCE_WIDTH_BELOW_METERS = 0.05;

    /** Permiso exclusivo (admin/superadmin) para añadir rollos a una factura ya recepcionada. */
    public const PERMISSION_ADD_ROLLS_AFTER_APPROVAL = 'agregar rollos recepcion aprobada';

    public const ROLL_NUMBER_PREFIX = 'RL';

    /**
     * Devuelve el siguiente número de rollo SIN reservarlo, para mostrarlo en el
     * formulario. No garantiza unicidad: si dos usuarios abren el formulario a la
     * vez verán el mismo valor. El número definitivo se toma con reserveRollNumber()
     * al guardar.
     */
    public function nextRollNumber(): string
    {
        $last = (int) DB::table('roll_number_sequences')
            ->where('prefix', self::ROLL_NUMBER_PREFIX)
            ->value('last_number');

        return self::ROLL_NUMBER_PREFIX.($last + 1);
    }

    /**
     * Reserva de forma atómica el siguiente número de rollo. El incremento y la
     * lectura ocurren en la misma sentencia, así que dos peticiones concurrentes
     * nunca obtienen el mismo valor.
     */
    public function reserveRollNumber(): string
    {
        // La fila la crea la migración; este insert es el camino defensivo por si la
        // secuencia no existe todavía en un entorno recién montado.
        DB::table('roll_number_sequences')->insertOrIgnore([
            'prefix' => self::ROLL_NUMBER_PREFIX,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () {
            // lockForUpdate bloquea la fila hasta el commit: una segunda petición
            // simultánea espera aquí y lee el valor ya incrementado, nunca el mismo.
            $last = (int) DB::table('roll_number_sequences')
                ->where('prefix', self::ROLL_NUMBER_PREFIX)
                ->lockForUpdate()
                ->value('last_number');

            $next = $last + 1;

            DB::table('roll_number_sequences')
                ->where('prefix', self::ROLL_NUMBER_PREFIX)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return self::ROLL_NUMBER_PREFIX.$next;
        });
    }

    /**
     * Decide con qué número se guarda un rollo nuevo.
     *
     * El formulario muestra un preview no reservado, así que entre que el usuario lo
     * ve y guarda, otro puede haberse quedado con ese número. En ese caso se toma el
     * siguiente de la secuencia en vez de fallar. Un número escrito a mano se respeta
     * tal cual (la regla `unique` del FormRequest ya rechaza los que colisionan).
     */
    protected function resolveRollNumberForCreation(?string $rollNumber): string
    {
        $rollNumber = trim((string) $rollNumber);

        if ($rollNumber === '') {
            return $this->reserveRollNumber();
        }

        $isGenerated = (bool) preg_match('/^'.self::ROLL_NUMBER_PREFIX.'\d+$/', $rollNumber);

        if ($isGenerated && FabricRoll::where('roll_number', $rollNumber)->exists()) {
            return $this->reserveRollNumber();
        }

        if ($isGenerated) {
            // Mantiene la secuencia por delante de lo ya insertado, para que el próximo
            // preview no vuelva a proponer un número que acaba de ocuparse.
            $this->bumpRollNumberSequenceTo((int) substr($rollNumber, strlen(self::ROLL_NUMBER_PREFIX)));
        }

        return $rollNumber;
    }

    protected function bumpRollNumberSequenceTo(int $number): void
    {
        DB::table('roll_number_sequences')
            ->where('prefix', self::ROLL_NUMBER_PREFIX)
            ->where('last_number', '<', $number)
            ->update(['last_number' => $number, 'updated_at' => now()]);
    }

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
            'creditNoteLinks.creditNote',
        ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseDocumentItem.item', function ($article) use ($search) {
                        $article->where('description', 'like', "%{$search}%")
                            ->orWhere('reference', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'available') {
                $query->whereRaw('quantity > actual_output');
            } elseif ($filters['status'] === 'depleted') {
                $query->whereRaw('quantity <= actual_output');
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('entry_date', 'desc')->paginate($perPage);
    }

    public function create(array $data): FabricRoll
    {
        return DB::transaction(function () use ($data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;
            $data['roll_width'] = $data['roll_width'] ?? 0;
            $data['approval_status'] = $data['approval_status'] ?? FabricRoll::APPROVAL_PENDING;
            $data['roll_number'] = $this->resolveRollNumberForCreation($data['roll_number'] ?? null);

            $fabricRoll = FabricRoll::create($data);

            if ($fabricRoll->purchase_document_item_id) {
                $this->syncToleranceStatusForLine((int) $fabricRoll->purchase_document_item_id);
                $fabricRoll->refresh();
            }

            $fabricRoll->load([
                'item',
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
                'creditNoteLinks.creditNote',
            ]);

            return $fabricRoll;
        });
    }

    public function findOrFail($id): FabricRoll
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])->findOrFail($id);
    }

    /**
     * Actualiza un rollo de factura mientras su recepción sigue abierta.
     *
     * Reserva (partial_output) y consumo (actual_output) nunca se editan aquí: solo los
     * cambian los flujos trazables (programación, descargue, ajuste). Un rollo sin factura
     * (legacy/importado) no pasa por recepción: se corrige con "Corregir cantidad" o ajustes.
     *
     * @throws ValidationException si la factura ya está recepcionada o el rollo tiene descargues
     */
    public function update(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        if (! $fabricRoll->purchase_document_item_id) {
            throw ValidationException::withMessages([
                'general' => ['Este rollo no pertenece a una factura: su cantidad se corrige con "Corregir cantidad" o con un ajuste, que quedan trazados.'],
            ]);
        }

        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);
        $this->ensureRollEditable($fabricRoll);

        // Mover el rollo a otra línea o factura esquivaría los controles de tolerancia y recepción.
        if (
            isset($data['purchase_document_item_id'])
            && (int) $data['purchase_document_item_id'] !== (int) $fabricRoll->purchase_document_item_id
        ) {
            throw ValidationException::withMessages([
                'purchase_document_item_id' => ['No se puede cambiar la línea de factura de un rollo ya registrado.'],
            ]);
        }

        unset($data['partial_output'], $data['actual_output']);

        return DB::transaction(function () use ($fabricRoll, $data) {
            $previousLineId = $fabricRoll->purchase_document_item_id;

            $fabricRoll->update($data);

            foreach (array_unique(array_filter([$previousLineId, $fabricRoll->purchase_document_item_id])) as $lineId) {
                $this->syncToleranceStatusForLine((int) $lineId);
            }
            $fabricRoll->refresh();

            $fabricRoll->load([
                'item',
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
                'creditNoteLinks.creditNote',
            ]);

            return $fabricRoll;
        });
    }

    public function delete(FabricRoll $fabricRoll): void
    {
        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);
        $this->ensureRollEditable($fabricRoll);

        if ((float) $fabricRoll->partial_output > FabricRollBalance::EPSILON) {
            throw ValidationException::withMessages([
                'general' => ['No se puede eliminar el rollo: tiene '.FabricRollBalance::meters((float) $fabricRoll->partial_output).' m reservados en programaciones. Libere esas reservas primero.'],
            ]);
        }

        DB::transaction(function () use ($fabricRoll) {
            $lineId = $fabricRoll->purchase_document_item_id;

            // Un rollo recibido con ítem ya tiene su saldo en el kardex: se da de baja con
            // movimiento, para que el inventario del ítem no conserve metros de un rollo borrado.
            $real = FabricRollBalance::real($fabricRoll);
            if ($fabricRoll->approval_status === FabricRoll::APPROVAL_APPROVED && $real > FabricRollBalance::EPSILON) {
                $this->createRollMovement(
                    $fabricRoll,
                    'salida',
                    $real,
                    'Baja por eliminación del rollo #'.$fabricRoll->roll_number,
                );
            }

            $fabricRoll->delete();

            if ($lineId) {
                $this->syncToleranceStatusForLine((int) $lineId);
            }
        });
    }

    /**
     * No permite eliminar ni editar un rollo si la factura ya está recepcionada.
     *
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApprovedForRoll(FabricRoll $fabricRoll): void
    {
        $fabricRoll->loadMissing('purchaseDocumentItem.document');
        $doc = $fabricRoll->purchaseDocumentItem?->document;
        if (! $doc) {
            return;
        }

        if ($doc->status === 'approved') {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura ya está recepcionada.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($doc)) {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
            ]);
        }

        // Un rollo con decisión tomada (rechazado, diferencia aprobada) o enlazado a una nota crédito
        // es evidencia de la recepción: no puede editarse ni borrarse para esquivar esa decisión.
        if (
            $fabricRoll->approval_status !== FabricRoll::APPROVAL_PENDING
            || in_array($fabricRoll->tolerance_status, [FabricRoll::TOLERANCE_REJECTED, FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE], true)
            || $fabricRoll->creditNoteLinks()->exists()
        ) {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: ya tiene una decisión de recepción (rechazado, diferencia aprobada o nota crédito asociada).'],
            ]);
        }
    }

    /**
     * Rollo con descargues: queda bloqueado para edición directa (cantidad, ítem, precio,
     * borrado). Solo admite operaciones trazables: descargue, ajuste y corrección de descargue.
     */
    public function hasDischarges(FabricRoll $fabricRoll): bool
    {
        return FabricRollAdjustment::where('fabric_roll_id', $fabricRoll->id)->where('type', 'salida')->exists()
            || ProductionRollConsumption::where('fabric_roll_id', $fabricRoll->id)->exists()
            || FabricRollLengthDifference::where('fabric_roll_id', $fabricRoll->id)->exists();
    }

    /**
     * @throws ValidationException
     */
    public function ensureRollEditable(FabricRoll $fabricRoll): void
    {
        if ($this->hasDischarges($fabricRoll)) {
            throw ValidationException::withMessages([
                'general' => ["El rollo #{$fabricRoll->roll_number} ya tiene descargues: no se puede editar directamente. Use un ajuste de entrada/salida o corrija el descargue; ambos quedan trazados."],
            ]);
        }
    }

    /**
     * Solo un rollo recibido en inventario (aprobado y sin rechazo pendiente) puede
     * reservarse, descargarse o ajustarse. Un movimiento sobre un rollo pendiente se
     * contaba dos veces: al ajustarlo y otra vez al aprobar la recepción con su cantidad.
     *
     * @throws ValidationException
     */
    protected function ensureRollOperable(FabricRoll $roll): void
    {
        if ($roll->approval_status !== FabricRoll::APPROVAL_APPROVED) {
            throw ValidationException::withMessages([
                'general' => ["El rollo #{$roll->roll_number} todavía no está recibido en inventario (recepción pendiente o rechazada): no se puede reservar, descargar ni ajustar."],
            ]);
        }

        if ($roll->tolerance_status === FabricRoll::TOLERANCE_REJECTED && $roll->resolved_at === null) {
            throw ValidationException::withMessages([
                'general' => ["El rollo #{$roll->roll_number} está restringido por rechazo en recepción."],
            ]);
        }
    }

    protected function rollItem(FabricRoll $roll): ?Item
    {
        return $roll->item ?? $roll->purchaseDocumentItem?->item;
    }

    /**
     * Bodega donde vive el saldo del ítem: la primera asignada (la misma que eligió la
     * recepción). Nunca se inventa una: un ítem sin bodega bloquea la operación para que
     * el consumo real no quede fuera del kardex.
     *
     * @throws ValidationException
     */
    protected function rollWarehouseId(Item $item, FabricRoll $roll): int
    {
        $warehouseId = WarehouseItem::where('item_id', $item->id)->orderBy('id')->value('warehouse_id');

        if (! $warehouseId) {
            throw ValidationException::withMessages([
                'item_id' => ["El ítem \"{$item->name}\" no tiene bodega asignada. Configure la bodega del ítem antes de operar el rollo #{$roll->roll_number}."],
            ]);
        }

        return (int) $warehouseId;
    }

    /**
     * Movimiento de kardex del rollo. Sin ítem vinculado no hay movimiento todavía: al
     * vincularlo, relinkMainFabric() carga el saldo real del rollo.
     *
     * Las entradas se valoran al precio del rollo (o al PP vigente); las salidas al PP.
     * Un rechazo del kardex (stock insuficiente, período cerrado) vuelve como error de
     * validación en vez de un 500 o de omitirse en silencio.
     *
     * @throws ValidationException
     */
    protected function createRollMovement(
        FabricRoll $roll,
        string $type,
        float $quantity,
        string $notes,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?Item $item = null,
        ?float $unitPrice = null,
    ): ?InventoryMovement {
        $quantity = FabricRollBalance::meters($quantity);
        $item ??= $this->rollItem($roll);

        if (! $item || $quantity <= 0) {
            return null;
        }

        $warehouseId = $this->rollWarehouseId($item, $roll);
        $weightedPrice = $this->movementService->getCurrentWeightedPrice($item->id, $warehouseId);
        $rollPrice = (float) ($roll->price_per_meter ?? $roll->purchaseDocumentItem?->price ?? 0);

        $unitPrice ??= $type === 'entrada'
            ? ($rollPrice > 0 ? $rollPrice : $weightedPrice)
            : ($weightedPrice > 0 ? $weightedPrice : $rollPrice);

        try {
            return $this->movementService->createMovement([
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantity,
                'unit_price' => max(0.0001, (float) $unitPrice),
                'date' => now()->toDateString(),
                'notes' => $notes,
                'source_type' => $sourceType ?? FabricRoll::class,
                'source_id' => $sourceId ?? $roll->id,
                'source_reference' => $roll->roll_number,
                'created_by_user_id' => Auth::id(),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => ["Kardex del rollo #{$roll->roll_number}: ".$e->getMessage()],
            ]);
        }
    }

    /**
     * Refleja en el kardex el cambio de saldo real de un rollo (y de ítem, si cambió).
     * Solo aplica a rollos recibidos: los pendientes entran completos al aprobar la recepción.
     */
    protected function syncRollKardex(FabricRoll $roll, ?Item $oldItem, float $oldReal, ?Item $newItem, float $newReal, string $notes): ?InventoryMovement
    {
        if ($roll->approval_status !== FabricRoll::APPROVAL_APPROVED) {
            return null;
        }

        if ($oldItem?->id === $newItem?->id) {
            $delta = FabricRollBalance::meters($newReal - $oldReal);
            if (abs($delta) <= FabricRollBalance::EPSILON) {
                return null;
            }

            return $this->createRollMovement($roll, $delta > 0 ? 'entrada' : 'salida', abs($delta), $notes, item: $newItem);
        }

        if ($oldItem && $oldReal > FabricRollBalance::EPSILON) {
            $this->createRollMovement($roll, 'salida', $oldReal, $notes.' (sale del ítem anterior)', item: $oldItem);
        }

        return $newItem && $newReal > FabricRollBalance::EPSILON
            ? $this->createRollMovement($roll, 'entrada', $newReal, $notes, item: $newItem)
            : null;
    }

    /**
     * Vincula (o cambia) la tela principal del rollo.
     *
     * El primer vínculo siempre se permite, aunque el rollo ya tenga descargues: es el
     * paso que carga en el kardex el saldo real de un rollo que se venía consumiendo sin
     * ítem. Cambiar un ítem ya vinculado es una edición: con descargues queda bloqueada;
     * sin ellos, el saldo se traslada del ítem anterior al nuevo.
     *
     * @throws ValidationException
     */
    public function relinkMainFabric(FabricRoll $fabricRoll, int $itemId): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $itemId) {
            $roll = FabricRoll::with('item')->lockForUpdate()->findOrFail($fabricRoll->id);
            $previousItem = $roll->item;

            if ($previousItem && (int) $previousItem->id === $itemId) {
                return $this->loadRollRelations($roll);
            }

            if ($previousItem) {
                $this->ensureRollEditable($roll);
            }

            $newItem = Item::findOrFail($itemId);
            $real = FabricRollBalance::real($roll);

            $roll->update(['item_id' => $itemId]);

            $movement = $this->syncRollKardex(
                $roll,
                $previousItem,
                $previousItem ? $real : 0.0,
                $newItem,
                $real,
                $previousItem
                    ? 'Cambio de tela principal del rollo '.$roll->roll_number
                    : 'Entrada por vinculación inicial de rollo '.$roll->roll_number,
            );

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                'Cambio de ítem vinculado (anterior: '.($previousItem?->id ?? 'ninguno').', nuevo: '.$itemId.')',
                inventoryMovementId: $movement?->id,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            return $this->loadRollRelations($roll);
        });
    }

    protected function loadRollRelations(FabricRoll $roll): FabricRoll
    {
        return $roll->load([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);
    }

    /**
     * Importa rollos históricos desde un CSV de 15 columnas.
     * Omite rollos con roll_number duplicado. item_id = null (se vincula después).
     *
     * @param  array<int, array>  $rows  Filas ya parseadas (arrays indexados 0-14)
     * @return array{inserted:int, skipped:int, errors:list<array{row:int|null,message:string}>}
     */
    public function importFromCsv(array $rows): array
    {
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $insertedIds = [];
        $insertedRollNumbers = [];

        $existingRolls = DB::table('fabric_rolls')
            ->pluck('roll_number')
            ->flip()
            ->toArray();

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $rollNumber = trim((string) ($row[0] ?? ''));

            if ($rollNumber === '') {
                $skipped++;

                continue;
            }

            // El duplicado se reporta, no se omite en silencio: antes el usuario solo
            // veía el contador de "omitidos" sin saber cuáles ni por qué, mezclado con
            // filas vacías y cantidades inválidas.
            if (isset($existingRolls[$rollNumber])) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ya existe (duplicado en el archivo o en el sistema). Se omite."];
                $skipped++;

                continue;
            }

            $baseTela = strtoupper(trim((string) ($row[4] ?? '')));
            $tela = strtoupper(trim((string) ($row[5] ?? '')));
            $description = $baseTela !== '' && $tela !== ''
                ? "{$baseTela} - {$tela}"
                : ($baseTela ?: $tela);

            $entryDate = $this->parseCsvDate((string) ($row[14] ?? ''));

            $quantity = (float) str_replace(',', '.', (string) ($row[9] ?? 0));
            $partialOutput = (float) str_replace(',', '.', (string) ($row[10] ?? 0));
            $actualOutput = (float) str_replace(',', '.', (string) ($row[12] ?? 0));

            if ($quantity <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': cantidad debe ser mayor a 0."];
                $skipped++;

                continue;
            }

            try {
                $insertedIds[] = DB::table('fabric_rolls')->insertGetId([
                    'roll_number' => $rollNumber,
                    'location' => trim((string) ($row[1] ?? '')) ?: null,
                    'description' => $description ?: null,
                    'internal_color_name' => strtoupper(trim((string) ($row[6] ?? ''))) ?: null,
                    'internal_color_code' => trim((string) ($row[7] ?? '')) ?: null,
                    'roll_width' => (float) str_replace(',', '.', (string) ($row[8] ?? 0)) ?: null,
                    'quantity' => $quantity,
                    'received_quantity' => $quantity,
                    'partial_output' => $partialOutput,
                    'actual_output' => $actualOutput,
                    'entry_date' => $entryDate,
                    'approval_status' => FabricRoll::APPROVAL_APPROVED,
                    'item_id' => null,
                    'purchase_document_item_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existingRolls[$rollNumber] = true;
                $insertedRollNumbers[] = $rollNumber;
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ".$e->getMessage()];
                $skipped++;
            }
        }

        // La inserción por query builder no dispara el AuditObserver: se registra un resumen.
        app(AuditLogService::class)->logBulk(
            FabricRoll::class,
            'created',
            $insertedIds,
            ['roll_numbers' => $insertedRollNumbers, 'approval_status' => FabricRoll::APPROVAL_APPROVED],
            'Importación de rollos históricos desde CSV',
        );

        return compact('inserted', 'skipped', 'errors');
    }

    private function parseCsvDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return now()->toDateString();
        }

        // d/m/Y H:i or d/m/Y
        foreach (['d/m/Y H:i', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        // fallback: strtotime
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : now()->toDateString();
    }

    /**
     * Corrige el metraje vigente de un rollo recibido que todavía no tiene descargues.
     * Crea el movimiento de inventario por el delta (entrada si sube, salida si baja) y
     * deja el registro en fabric_roll_adjustments. Lo recibido (received_quantity) no cambia.
     *
     * @throws ValidationException
     */
    public function correctQuantity(FabricRoll $fabricRoll, float $newQuantity, string $reason): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $newQuantity, $reason) {
            $roll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($fabricRoll->id);
            $this->ensureRollOperable($roll);
            $this->ensureRollEditable($roll);

            $newQuantity = FabricRollBalance::meters($newQuantity);
            $currentQty = (float) $roll->quantity;
            $delta = FabricRollBalance::meters($newQuantity - $currentQty);

            if (abs($delta) <= FabricRollBalance::EPSILON) {
                return $roll;
            }

            $actualOutput = (float) $roll->actual_output;
            $reservedFloor = FabricRollBalance::meters($actualOutput + (float) $roll->partial_output);
            if ($newQuantity < $reservedFloor - FabricRollBalance::EPSILON) {
                throw ValidationException::withMessages([
                    'new_quantity' => ['La nueva cantidad no puede ser menor que lo ya consumido más lo reservado ('.$reservedFloor.' mts).'],
                ]);
            }

            $movement = $this->createRollMovement(
                $roll,
                $delta > 0 ? 'entrada' : 'salida',
                abs($delta),
                'Corrección de cantidad rollo #'.$roll->roll_number.'. Motivo: '.$reason,
            );

            $roll->update(['quantity' => $newQuantity]);
            $roll->refresh();

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                $currentQty,
                $newQuantity,
                $reason,
                inventoryMovementId: $movement?->id,
                actualOutputBefore: $actualOutput,
                actualOutputAfter: $actualOutput,
                quantity: $delta,
            );

            return $roll;
        });
    }

    /**
     * Ajuste general del rollo desde el inventario.
     *  - salida: consume del disponible LIBRE (no toma metros reservados para otras órdenes;
     *    esos se descargan desde el descargue por orden).
     *  - entrada: suma metraje al rollo (lo recibido no cambia).
     *
     * @throws ValidationException
     */
    public function discharge(FabricRoll $fabricRoll, string $type, float $quantity, string $reason, ?int $productionOrderId, ?string $reference): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $type, $quantity, $reason, $productionOrderId, $reference) {
            $roll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($fabricRoll->id);
            $this->ensureRollOperable($roll);

            $quantity = FabricRollBalance::meters($quantity);
            $quantityBefore = (float) $roll->quantity;
            $actualOutputBefore = (float) $roll->actual_output;

            if ($type === 'salida') {
                $free = FabricRollBalance::free($roll);
                if ($quantity > $free + FabricRollBalance::EPSILON) {
                    $reserved = FabricRollBalance::meters((float) $roll->partial_output);
                    throw ValidationException::withMessages([
                        'quantity' => ["La salida ({$quantity} m) supera el disponible libre del rollo ({$free} m). Hay {$reserved} m reservados para órdenes: descárguelos desde el descargue por orden."],
                    ]);
                }

                $movement = $this->createRollMovement(
                    $roll,
                    'salida',
                    $quantity,
                    'Ajuste salida rollo #'.$roll->roll_number.'. '.$reason,
                    $productionOrderId ? ProductionOrder::class : null,
                    $productionOrderId,
                );

                $roll->actual_output = FabricRollBalance::meters($actualOutputBefore + $quantity);
                $roll->save();

                $adjustment = $this->recordAdjustmentWithMovement(
                    $roll,
                    'salida',
                    $quantityBefore,
                    (float) $roll->quantity,
                    $reason,
                    reference: $reference,
                    inventoryMovementId: $movement?->id,
                    actualOutputBefore: $actualOutputBefore,
                    actualOutputAfter: (float) $roll->actual_output,
                    quantity: $quantity,
                );

                $this->recordRollConsumption($roll, $quantity, $productionOrderId, ProductionRollConsumption::SOURCE_ADJUSTMENT, $adjustment->id);
            } else {
                $movement = $this->createRollMovement(
                    $roll,
                    'entrada',
                    $quantity,
                    'Ajuste entrada rollo #'.$roll->roll_number.'. '.$reason,
                );

                $roll->quantity = FabricRollBalance::meters($quantityBefore + $quantity);
                $roll->save();

                $this->recordAdjustmentWithMovement(
                    $roll,
                    'entrada',
                    $quantityBefore,
                    (float) $roll->quantity,
                    $reason,
                    reference: $reference,
                    inventoryMovementId: $movement?->id,
                    actualOutputBefore: $actualOutputBefore,
                    actualOutputAfter: $actualOutputBefore,
                    quantity: $quantity,
                );
            }

            return $roll->refresh();
        });
    }

    /**
     * Programa (reserva) metros de un rollo bajo un código de orden escrito a mano —
     * mientras el flujo de Órdenes de Producción no está operativo. La reserva sale del
     * disponible libre del rollo (no de metros ya reservados para otra orden).
     */
    public function programManual(int $fabricRollId, string $manualOrderCode, float $programmedMeters, ?string $notes = null): ManualRollProgramming
    {
        return DB::transaction(fn () => $this->applyProgramManual($fabricRollId, $manualOrderCode, $programmedMeters, $notes));
    }

    /**
     * Programa varios rollos de una sola vez bajo el mismo código de orden, en una
     * única transacción: si cualquier rollo del lote no tiene disponibilidad
     * suficiente, se revierte el lote completo (ningún rollo queda reservado a medias).
     *
     * @param  array<int, array{fabric_roll_id: int, programmed_meters: float}>  $rolls
     * @return Collection<int, ManualRollProgramming>
     */
    public function programManualBatch(string $manualOrderCode, array $rolls, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($manualOrderCode, $rolls, $notes) {
            return collect($rolls)->map(
                fn (array $row) => $this->applyProgramManual(
                    (int) $row['fabric_roll_id'],
                    $manualOrderCode,
                    (float) $row['programmed_meters'],
                    $notes,
                ),
            );
        });
    }

    /**
     * Cuerpo de programManual(), sin abrir su propia transacción — para poder
     * reutilizarlo dentro de la transacción única de programManualBatch().
     */
    protected function applyProgramManual(int $fabricRollId, string $manualOrderCode, float $programmedMeters, ?string $notes): ManualRollProgramming
    {
        $roll = FabricRoll::lockForUpdate()->findOrFail($fabricRollId);
        $this->ensureRollOperable($roll);

        // Cada llamada crea una línea propia: un mismo rollo puede programarse más de
        // una vez bajo el mismo código de orden (p. ej. en dos lotes de corte
        // distintos), sin que la segunda programación sobrescriba ni fusione la
        // cantidad de la primera.
        $programmedMeters = FabricRollBalance::meters($programmedMeters);
        $available = FabricRollBalance::free($roll);

        if ($programmedMeters > $available + FabricRollBalance::EPSILON) {
            throw ValidationException::withMessages([
                'programmed_meters' => ["Rollo {$roll->roll_number}: disponibilidad insuficiente ({$available} m disponibles, se intentan reservar {$programmedMeters} m)."],
            ]);
        }

        $currentPartial = (float) $roll->partial_output;
        $newPartial = FabricRollBalance::meters($currentPartial + $programmedMeters);

        $roll->partial_output = $newPartial;
        $roll->save();

        /** @var ManualRollProgramming $line */
        $line = ManualRollProgramming::create([
            'fabric_roll_id' => $fabricRollId,
            'manual_order_code' => $manualOrderCode,
            'programmed_meters' => $programmedMeters,
            'notes' => $notes,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->recordAdjustmentWithMovement(
            $roll,
            'correction',
            (float) $roll->quantity,
            (float) $roll->quantity,
            $notes ?: "Programación manual {$manualOrderCode}: {$programmedMeters} m reservados",
            reference: "manual_programming::{$line->id}",
            partialBefore: $currentPartial,
            partialAfter: $newPartial,
            actualOutputBefore: (float) $roll->actual_output,
            actualOutputAfter: (float) $roll->actual_output,
        );

        return $line;
    }

    /**
     * Edita una línea de programación manual ya creada (cantidad y/o notas). La reserva
     * del rollo se ajusta por el cambio de lo que la línea todavía tiene pendiente
     * (programado − consumido − liberado), no por el cambio bruto de lo programado.
     *
     * @throws ValidationException
     */
    public function updateManualProgram(ManualRollProgramming $manualRollProgramming, float $newProgrammedMeters, ?string $notes): ManualRollProgramming
    {
        return DB::transaction(function () use ($manualRollProgramming, $newProgrammedMeters, $notes) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);
            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

            $newProgrammedMeters = FabricRollBalance::meters($newProgrammedMeters);
            $oldProgrammed = (float) $line->programmed_meters;
            $consumed = (float) $line->consumed_meters;

            // Bajar lo programado por debajo de lo consumido convertiría consumo normal en
            // sobreconsumo sin motivo. Subirlo (p. ej. para formalizar un sobreconsumo) sí se permite.
            if ($newProgrammedMeters < $oldProgrammed - FabricRollBalance::EPSILON && $newProgrammedMeters < $consumed - FabricRollBalance::EPSILON) {
                throw ValidationException::withMessages([
                    'programmed_meters' => ['La cantidad programada no puede ser menor que lo ya consumido ('.FabricRollBalance::meters($consumed).' m).'],
                ]);
            }

            $reservedBefore = $line->remaining_meters;
            $reservedAfter = max(0.0, round($newProgrammedMeters - $consumed - (float) $line->released_meters, 6));
            $delta = FabricRollBalance::meters($reservedAfter - $reservedBefore);

            if ($delta > FabricRollBalance::EPSILON) {
                $this->ensureRollOperable($roll);
                $available = FabricRollBalance::free($roll);
                if ($delta > $available + FabricRollBalance::EPSILON) {
                    throw ValidationException::withMessages([
                        'programmed_meters' => ["Rollo {$roll->roll_number}: disponibilidad insuficiente ({$available} m disponibles para ampliar la reserva en {$delta} m)."],
                    ]);
                }
            }

            $currentPartial = (float) $roll->partial_output;
            $newPartial = FabricRollBalance::meters(max(0.0, $currentPartial + $delta));

            $roll->partial_output = $newPartial;
            $roll->save();

            $line->programmed_meters = $newProgrammedMeters;
            $line->notes = $notes;
            $line->save();

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                "Edición de programación manual {$line->manual_order_code}: {$oldProgrammed} m -> {$newProgrammedMeters} m",
                reference: "manual_programming::{$line->id}",
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            return $line->refresh();
        });
    }

    /**
     * Sincroniza la reserva de rollos (partial_output) al guardar las líneas de la ficha
     * técnica de una OP. Trabaja sobre lo PENDIENTE de cada línea (programado − liberado
     * por el corte): editar o borrar una línea ya descargada no vuelve a liberar la reserva
     * que el corte ya liberó (antes se liberaba dos veces y el max(0) ocultaba el negativo).
     *
     * Debe llamarse dentro de la misma transacción que guarda las líneas.
     *
     * @param  array<int, array<string, mixed>>  $existingLines  líneas actuales (store->read)
     * @param  array<int, array<string, mixed>>  $nextLines  líneas a guardar
     * @return array<int, array<string, mixed>> $nextLines con releasedMeters resuelto por línea
     *
     * @throws ValidationException
     */
    public function syncSheetReservations(array $existingLines, array $nextLines): array
    {
        $existingById = [];
        foreach ($existingLines as $line) {
            $id = trim((string) ($line['id'] ?? ''));
            if ($id !== '') {
                $existingById[$id] = $line;
            }
        }

        // Lo liberado pertenece a la línea en ESE rollo: si la línea cambia de rollo, empieza de cero.
        foreach ($nextLines as $index => $line) {
            $previous = $existingById[trim((string) ($line['id'] ?? ''))] ?? null;
            $sameRoll = $previous !== null && (string) ($previous['rollId'] ?? '') === (string) ($line['rollId'] ?? '');
            $nextLines[$index]['releasedMeters'] = $sameRoll ? (float) ($previous['releasedMeters'] ?? 0) : 0.0;
        }

        $oldByRoll = $this->sheetPendingByRoll($existingLines);
        $newByRoll = $this->sheetPendingByRoll($nextLines);
        $rollIds = array_values(array_unique(array_merge(array_keys($oldByRoll), array_keys($newByRoll))));
        sort($rollIds);

        if ($rollIds === []) {
            return $nextLines;
        }

        $rolls = FabricRoll::whereIn('id', $rollIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($rollIds as $rollId) {
            $roll = $rolls->get($rollId);
            if (! $roll) {
                continue;
            }

            $newMeters = $newByRoll[$rollId] ?? 0.0;
            $delta = FabricRollBalance::meters($newMeters - ($oldByRoll[$rollId] ?? 0.0));
            if (abs($delta) <= FabricRollBalance::EPSILON) {
                continue;
            }

            if ($delta > 0) {
                $this->ensureRollOperable($roll);
                $available = FabricRollBalance::free($roll);
                if ($delta > $available + FabricRollBalance::EPSILON) {
                    throw ValidationException::withMessages([
                        'rolls' => ["Rollo {$roll->roll_number}: disponibilidad parcial insuficiente ({$available} m disponibles, se intentan asignar {$newMeters} m)."],
                    ]);
                }
            }

            $roll->partial_output = FabricRollBalance::meters(max(0.0, (float) $roll->partial_output + $delta));
            $roll->save();
        }

        return $nextLines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, float> fabric_roll_id => metros pendientes de reserva
     */
    protected function sheetPendingByRoll(array $lines): array
    {
        $byRoll = [];
        foreach ($lines as $line) {
            $rollId = trim((string) ($line['rollId'] ?? ''));
            if ($rollId === '' || ! is_numeric($rollId)) {
                continue;
            }

            $programmed = is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : 0.0;
            $pending = max(0.0, $programmed - (float) ($line['releasedMeters'] ?? 0));
            $byRoll[(int) $rollId] = FabricRollBalance::meters(($byRoll[(int) $rollId] ?? 0.0) + $pending);
        }

        return $byRoll;
    }

    /**
     * Registra consumo real contra una línea programada manualmente. Puede llamarse
     * varias veces sobre la misma línea (descargue parcial): cada llamada crea su propio
     * FabricRollAdjustment + InventoryMovement.
     *
     * El metraje registrado del rollo no es confiable (puede sobrar o faltar), así que:
     *  - Sobreconsumo: se puede descargar más de lo programado, con motivo obligatorio.
     *    El exceso sale del disponible libre del rollo; nunca de reservas de otras órdenes.
     *  - Sobrante: si el libre no alcanza, el rollo trae más tela de la registrada. El
     *    descargue no se detiene: se registra un evento de diferencia de metraje con su
     *    entrada de kardex, pendiente de revisión.
     *  - Rollo terminado ($finishRoll): tras el descargue, el saldo que el sistema todavía
     *    tiene se registra como faltante y se liberan las reservas que quedaban sobre el rollo.
     *
     * @throws ValidationException
     */
    public function dischargeManual(ManualRollProgramming $manualRollProgramming, float $quantity, ?string $reason = null, bool $finishRoll = false): ManualRollProgramming
    {
        return DB::transaction(function () use ($manualRollProgramming, $quantity, $reason, $finishRoll) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);
            $roll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($line->fabric_roll_id);
            $this->ensureRollOperable($roll);

            $quantity = FabricRollBalance::meters($quantity);
            $reason = $this->cleanReason($reason);

            if ($quantity <= 0 && ! $finishRoll) {
                throw ValidationException::withMessages([
                    'quantity' => ['Ingresa una cantidad mayor a 0.'],
                ]);
            }

            $pending = FabricRollBalance::meters($line->remaining_meters);
            $fromReserve = min($quantity, $pending);
            $excess = FabricRollBalance::meters($quantity - $fromReserve);

            if ($excess > FabricRollBalance::EPSILON && $reason === null) {
                throw ValidationException::withMessages([
                    'reason' => ["Descargas {$excess} m más de lo programado para esta orden: indica el motivo del sobreconsumo."],
                ]);
            }

            $quantityBefore = (float) $roll->quantity;
            $partialBefore = (float) $roll->partial_output;
            $actualOutputBefore = (float) $roll->actual_output;

            $surplus = FabricRollBalance::meters(max(0.0, $excess - FabricRollBalance::free($roll)));
            $surplusMovement = null;
            if ($surplus > FabricRollBalance::EPSILON) {
                $surplusMovement = $this->createRollMovement(
                    $roll,
                    'entrada',
                    $surplus,
                    "Sobrante de metraje rollo #{$roll->roll_number} — Código {$line->manual_order_code}. {$reason}",
                );
                $roll->quantity = FabricRollBalance::meters($quantityBefore + $surplus);
            }

            $movement = $this->createRollMovement(
                $roll,
                'salida',
                $quantity,
                "Consumo manual rollo #{$roll->roll_number} — Código {$line->manual_order_code}",
                $line->production_order_id ? ProductionOrder::class : null,
                $line->production_order_id,
            );

            $roll->actual_output = FabricRollBalance::meters($actualOutputBefore + $quantity);
            $roll->partial_output = FabricRollBalance::meters(max(0.0, $partialBefore - $fromReserve));
            if ($surplus > FabricRollBalance::EPSILON) {
                $roll->length_review_status = FabricRoll::LENGTH_REVIEW_PENDING;
            }
            $roll->save();

            $line->consumed_meters = round((float) $line->consumed_meters + $quantity, 6);
            $line->save();

            $adjustment = null;
            if ($quantity > 0) {
                $adjustment = $this->recordAdjustmentWithMovement(
                    $roll,
                    'salida',
                    $quantityBefore,
                    (float) $roll->quantity,
                    $reason ?? "Descargue manual {$line->manual_order_code}: {$quantity} m",
                    reference: "manual_programming::{$line->id}",
                    inventoryMovementId: $movement?->id,
                    partialBefore: $partialBefore,
                    partialAfter: (float) $roll->partial_output,
                    actualOutputBefore: $actualOutputBefore,
                    actualOutputAfter: (float) $roll->actual_output,
                    quantity: $quantity,
                );

                $this->recordRollConsumption($roll, $quantity, $line->production_order_id, ProductionRollConsumption::SOURCE_MANUAL, $adjustment->id);
            }

            $recorded = [];
            if ($surplus > FabricRollBalance::EPSILON) {
                $recorded[] = $this->recordLengthDifference(
                    $roll,
                    FabricRollLengthDifference::TYPE_SURPLUS,
                    $surplus,
                    $quantityBefore,
                    $quantityBefore + $surplus,
                    (string) $reason,
                    $adjustment?->id,
                    $surplusMovement,
                    manualLine: $line,
                    productionOrderId: $line->production_order_id,
                );
            }

            if ($finishRoll) {
                $recorded[] = $this->finishRoll($roll, $reason, $adjustment?->id, $line, $line->production_order_id);
            }

            // Diferencias de metraje registradas en ESTE descargue, para informarlas al usuario.
            return $line->refresh()->setRelation('recordedDifferences', collect(array_filter($recorded)));
        });
    }

    /**
     * Corrige la cantidad de un descargue YA registrado (un evento del historial).
     *
     * La cantidad vigente del evento vive en fabric_roll_adjustments.quantity y se
     * actualiza con cada corrección, así que corregir dos veces el mismo descargue parte
     * siempre del valor vigente (antes se partía del original y la diferencia se aplicaba
     * otra vez). El kardex se ajusta por la diferencia con un movimiento propio de la
     * corrección; el movimiento original no se toca (los movimientos son inmutables).
     *
     * @throws ValidationException
     */
    public function correctManualDischarge(FabricRollAdjustment $fabricRollAdjustment, float $newQuantity, ?string $reason = null, ?ManualRollProgramming $expectedLine = null): FabricRollAdjustment
    {
        return DB::transaction(function () use ($fabricRollAdjustment, $newQuantity, $reason, $expectedLine) {
            $adjustment = FabricRollAdjustment::lockForUpdate()->findOrFail($fabricRollAdjustment->id);

            if ($adjustment->type !== 'salida' || ! str_starts_with((string) $adjustment->reference, 'manual_programming::')) {
                throw ValidationException::withMessages([
                    'discharge' => ['Este registro no corresponde a un descargue de programación manual.'],
                ]);
            }

            $lineId = (int) str_replace('manual_programming::', '', (string) $adjustment->reference);
            if ($expectedLine !== null && (int) $expectedLine->id !== $lineId) {
                throw ValidationException::withMessages([
                    'discharge' => ['El descargue no pertenece a esta programación.'],
                ]);
            }

            $line = ManualRollProgramming::lockForUpdate()->findOrFail($lineId);
            $roll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($line->fabric_roll_id);
            $this->ensureRollOperable($roll);

            $newQuantity = FabricRollBalance::meters($newQuantity);
            $reason = $this->cleanReason($reason);
            $oldQuantity = FabricRollBalance::meters((float) ($adjustment->quantity ?? ((float) $adjustment->actual_output_after - (float) $adjustment->actual_output_before)));
            $delta = FabricRollBalance::meters($newQuantity - $oldQuantity);

            if (abs($delta) <= FabricRollBalance::EPSILON) {
                return $adjustment;
            }

            $programmed = (float) $line->programmed_meters;
            $consumedBefore = (float) $line->consumed_meters;
            $consumedAfter = round($consumedBefore + $delta, 6);
            if ($consumedAfter < -FabricRollBalance::EPSILON) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad corregida no puede dejar el consumo total de la línea en negativo.'],
                ]);
            }

            $overBefore = max(0.0, $consumedBefore - $programmed);
            $overAfter = max(0.0, $consumedAfter - $programmed);
            if ($overAfter > $overBefore + FabricRollBalance::EPSILON && $reason === null) {
                throw ValidationException::withMessages([
                    'reason' => ['La corrección deja el descargue '.FabricRollBalance::meters($overAfter).' m por encima de lo programado ('.FabricRollBalance::meters($programmed).' m): indica el motivo.'],
                ]);
            }

            $reservedBefore = $line->remaining_meters;
            $reservedAfter = max(0.0, round($programmed - $consumedAfter - (float) $line->released_meters, 6));

            $quantityBefore = (float) $roll->quantity;
            $partialBefore = (float) $roll->partial_output;
            $actualOutputBefore = (float) $roll->actual_output;
            $surplus = 0.0;
            $surplusMovement = null;
            $orderSource = $line->production_order_id ? ProductionOrder::class : null;

            if ($delta > 0) {
                $needFromFree = FabricRollBalance::meters($delta - max(0.0, $reservedBefore - $reservedAfter));
                $surplus = FabricRollBalance::meters(max(0.0, $needFromFree - FabricRollBalance::free($roll)));

                if ($surplus > FabricRollBalance::EPSILON) {
                    if ($reason === null) {
                        throw ValidationException::withMessages([
                            'reason' => ["La corrección supera el metraje registrado del rollo en {$surplus} m: indica el motivo."],
                        ]);
                    }
                    $surplusMovement = $this->createRollMovement($roll, 'entrada', $surplus, "Sobrante de metraje rollo #{$roll->roll_number} (corrección de descargue #{$adjustment->id}). {$reason}");
                    $roll->quantity = FabricRollBalance::meters($quantityBefore + $surplus);
                    $roll->length_review_status = FabricRoll::LENGTH_REVIEW_PENDING;
                }

                $movement = $this->createRollMovement($roll, 'salida', $delta, "Corrección de descargue manual rollo #{$roll->roll_number} — Código {$line->manual_order_code}: {$oldQuantity} m -> {$newQuantity} m", $orderSource, $line->production_order_id);
            } else {
                $movement = $this->createRollMovement($roll, 'entrada', abs($delta), "Corrección de descargue manual rollo #{$roll->roll_number} — Código {$line->manual_order_code}: {$oldQuantity} m -> {$newQuantity} m", $orderSource, $line->production_order_id);
            }

            $roll->actual_output = FabricRollBalance::meters(max(0.0, $actualOutputBefore + $delta));
            $roll->partial_output = FabricRollBalance::meters(max(0.0, $partialBefore + ($reservedAfter - $reservedBefore)));
            $roll->save();

            $line->consumed_meters = $consumedAfter;
            $line->save();

            $correction = $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                $quantityBefore,
                (float) $roll->quantity,
                "Corrección de descargue #{$adjustment->id}: {$oldQuantity} m -> {$newQuantity} m".($reason !== null ? ". Motivo: {$reason}" : ''),
                reference: "manual_programming::{$line->id}",
                inventoryMovementId: $movement?->id,
                partialBefore: $partialBefore,
                partialAfter: (float) $roll->partial_output,
                actualOutputBefore: $actualOutputBefore,
                actualOutputAfter: (float) $roll->actual_output,
                quantity: $newQuantity,
                correctsAdjustmentId: $adjustment->id,
            );

            $adjustment->update(['quantity' => $newQuantity]);

            // El consumo reportado a la orden sigue al descargue corregido.
            $consumption = ProductionRollConsumption::where('fabric_roll_adjustment_id', $adjustment->id)->first();
            if ($consumption) {
                $consumption->update(['meters_consumed' => $newQuantity]);
            } else {
                $this->recordRollConsumption($roll, $delta, $line->production_order_id, ProductionRollConsumption::SOURCE_MANUAL, $correction->id);
            }

            if ($surplus > FabricRollBalance::EPSILON) {
                $this->recordLengthDifference(
                    $roll,
                    FabricRollLengthDifference::TYPE_SURPLUS,
                    $surplus,
                    $quantityBefore,
                    $quantityBefore + $surplus,
                    (string) $reason,
                    $correction->id,
                    $surplusMovement,
                    manualLine: $line,
                    productionOrderId: $line->production_order_id,
                );
            } elseif ($delta < 0) {
                // Si este descargue había registrado sobrante y ahora se consumió menos, el
                // sobrante (o parte) no existió: se revierte con un evento inverso ligado.
                $this->reverseSurplusOfDischarge($roll, $adjustment, abs($delta), $correction->id, $line);
            }

            return $adjustment->fresh();
        });
    }

    /**
     * Des-programa una línea manual antes de que se le haya registrado consumo,
     * liberando la reserva física del rollo. No permitido si ya se descargó algo.
     */
    public function unprogramManual(ManualRollProgramming $manualRollProgramming): void
    {
        DB::transaction(function () use ($manualRollProgramming) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);

            if ((float) $line->consumed_meters > FabricRollBalance::EPSILON) {
                throw ValidationException::withMessages([
                    'manual_roll_programming' => ['No se puede eliminar una programación con descargues ya registrados.'],
                ]);
            }

            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);
            $currentPartial = (float) $roll->partial_output;
            $newPartial = FabricRollBalance::meters(max(0.0, $currentPartial - $line->remaining_meters));
            $roll->update(['partial_output' => $newPartial]);

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                "Se elimina programación manual {$line->manual_order_code}",
                reference: "manual_programming::{$line->id}",
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            $line->delete();
        });
    }

    /**
     * Historial de descargues (type 'salida') de una línea manual, con su cantidad vigente
     * y cuántas veces se corrigió. La programación inicial también queda registrada con el
     * mismo reference (type 'correction'), pero no es un descargue.
     */
    public function manualHistory(ManualRollProgramming $manualRollProgramming): Collection
    {
        return FabricRollAdjustment::where('reference', "manual_programming::{$manualRollProgramming->id}")
            ->where('type', 'salida')
            ->with(['user:id,name', 'inventoryMovement:id,quantity'])
            ->withCount('corrections')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Registra (o re-registra) el consumo de corte de una OP. Idempotente: por rollo aplica
     * solo la diferencia contra lo que el corte ya había registrado para esa orden.
     *
     * - Al descargar un rollo se libera lo que la ficha técnica de la OP todavía reservaba
     *   en él (programado − liberado), leído de la base: no de lo que envía el cliente.
     * - Consumo mayor al disponible del rollo: se registra como sobrante de metraje.
     * - "Último descargue" del rollo: el saldo que el sistema todavía tiene es faltante.
     * - Un error de kardex cancela el guardado completo (antes se omitía en silencio).
     *
     * @param  array<int, array<string, mixed>>  $lines  líneas validadas del registro de corte
     *
     * @throws ValidationException
     */
    public function saveCutConsumption(ProductionOrder $order, array $lines): void
    {
        $rollCodes = collect($lines)
            ->map(fn (array $line) => trim((string) ($line['roll_code'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $rollIdsByCode = FabricRoll::whereIn('roll_number', $rollCodes)->pluck('id', 'roll_number');

        $newByRoll = [];
        $finishByRoll = [];
        $unknownCodes = [];
        foreach ($lines as $line) {
            $code = trim((string) ($line['roll_code'] ?? ''));
            $consumption = FabricRollBalance::meters((float) ($line['total_consumption'] ?? 0));
            if ($code === '') {
                continue;
            }

            $rollId = $rollIdsByCode[$code] ?? null;
            if ($rollId === null) {
                if ($consumption > 0) {
                    $unknownCodes[] = $code;
                }

                continue;
            }

            $rollId = (int) $rollId;
            if ($consumption > 0) {
                $newByRoll[$rollId] = FabricRollBalance::meters(($newByRoll[$rollId] ?? 0.0) + $consumption);
            }
            if (! empty($line['ultimo_descargue'])) {
                $finishByRoll[$rollId] = true;
            }
        }

        if ($unknownCodes !== []) {
            throw ValidationException::withMessages([
                'lines' => ['No existen los rollos: '.implode(', ', array_unique($unknownCodes)).'.'],
            ]);
        }

        DB::transaction(function () use ($order, $newByRoll, $finishByRoll) {
            $previousByRoll = ProductionRollConsumption::where('production_order_id', $order->id)
                ->where('source', ProductionRollConsumption::SOURCE_CUT)
                ->selectRaw('fabric_roll_id, SUM(meters_consumed) AS total')
                ->groupBy('fabric_roll_id')
                ->pluck('total', 'fabric_roll_id')
                ->map(fn ($total) => FabricRollBalance::meters((float) $total))
                ->all();

            $sheetPending = FabricRollBalance::sheetPendingByRollForOrder($order->id);
            $sheetIds = DB::table('technical_sheets')
                ->where('production_order_id', $order->id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();

            $rollIds = array_values(array_unique(array_merge(
                array_keys($newByRoll),
                array_keys($previousByRoll),
                array_keys($finishByRoll),
            )));
            sort($rollIds);

            $rolls = FabricRoll::with(['item', 'purchaseDocumentItem.item'])
                ->whereIn('id', $rollIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($rollIds as $rollId) {
                /** @var FabricRoll|null $roll */
                $roll = $rolls->get($rollId);
                if (! $roll) {
                    continue;
                }

                $newMeters = (float) ($newByRoll[$rollId] ?? 0.0);
                $delta = FabricRollBalance::meters($newMeters - (float) ($previousByRoll[$rollId] ?? 0.0));
                $toRelease = $newMeters > 0 ? (float) ($sheetPending[$rollId] ?? 0.0) : 0.0;
                $finish = isset($finishByRoll[$rollId]);

                if (abs($delta) <= FabricRollBalance::EPSILON && $toRelease <= FabricRollBalance::EPSILON && ! $finish) {
                    continue;
                }

                if ($delta > FabricRollBalance::EPSILON || $finish) {
                    $this->ensureRollOperable($roll);
                }

                $quantityBefore = (float) $roll->quantity;
                $partialBefore = (float) $roll->partial_output;
                $actualOutputBefore = (float) $roll->actual_output;

                if ($toRelease > FabricRollBalance::EPSILON) {
                    // Por instancia para que el AuditObserver registre cada línea liberada.
                    FabricRollProgrammingLine::whereIn('technical_sheet_id', $sheetIds)
                        ->where('fabric_roll_id', $rollId)
                        ->get()
                        ->each(fn (FabricRollProgrammingLine $line) => $line->update(['released_meters' => $line->programmed_meters]));
                    $roll->partial_output = FabricRollBalance::meters(max(0.0, $partialBefore - $toRelease));
                }

                $adjustment = null;
                $opLabel = "OP {$order->op_code}";

                if ($delta > FabricRollBalance::EPSILON) {
                    $surplus = FabricRollBalance::meters(max(0.0, $delta - FabricRollBalance::free($roll)));
                    $surplusMovement = null;
                    if ($surplus > FabricRollBalance::EPSILON) {
                        $surplusMovement = $this->createRollMovement($roll, 'entrada', $surplus, "Sobrante de metraje rollo #{$roll->roll_number} — consumo de corte {$opLabel}", ProductionOrder::class, $order->id);
                        $roll->quantity = FabricRollBalance::meters($quantityBefore + $surplus);
                        $roll->length_review_status = FabricRoll::LENGTH_REVIEW_PENDING;
                    }

                    $movement = $this->createRollMovement($roll, 'salida', $delta, "Consumo corte rollo #{$roll->roll_number} — {$opLabel}", ProductionOrder::class, $order->id);
                    $roll->actual_output = FabricRollBalance::meters($actualOutputBefore + $delta);
                    $roll->save();

                    $adjustment = $this->recordAdjustmentWithMovement(
                        $roll,
                        'salida',
                        $quantityBefore,
                        (float) $roll->quantity,
                        "Descargue corte {$opLabel}: {$delta} m",
                        reference: "cut_consumption::{$order->id}",
                        inventoryMovementId: $movement?->id,
                        partialBefore: $partialBefore,
                        partialAfter: (float) $roll->partial_output,
                        actualOutputBefore: $actualOutputBefore,
                        actualOutputAfter: (float) $roll->actual_output,
                        quantity: $delta,
                    );

                    if ($surplus > FabricRollBalance::EPSILON) {
                        $this->recordLengthDifference(
                            $roll,
                            FabricRollLengthDifference::TYPE_SURPLUS,
                            $surplus,
                            $quantityBefore,
                            $quantityBefore + $surplus,
                            "El consumo de corte {$opLabel} supera el metraje registrado del rollo.",
                            $adjustment->id,
                            $surplusMovement,
                            productionOrderId: $order->id,
                        );
                    }
                } elseif ($delta < -FabricRollBalance::EPSILON) {
                    $movement = $this->createRollMovement($roll, 'entrada', abs($delta), "Corrección corte rollo #{$roll->roll_number} — {$opLabel}", ProductionOrder::class, $order->id);
                    $roll->actual_output = FabricRollBalance::meters(max(0.0, $actualOutputBefore + $delta));
                    $roll->save();

                    $adjustment = $this->recordAdjustmentWithMovement(
                        $roll,
                        'correction',
                        $quantityBefore,
                        (float) $roll->quantity,
                        "Corrección descargue corte {$opLabel}: ".FabricRollBalance::meters($newMeters - $delta)." m -> {$newMeters} m",
                        reference: "cut_consumption::{$order->id}",
                        inventoryMovementId: $movement?->id,
                        partialBefore: $partialBefore,
                        partialAfter: (float) $roll->partial_output,
                        actualOutputBefore: $actualOutputBefore,
                        actualOutputAfter: (float) $roll->actual_output,
                        quantity: $delta,
                    );
                } else {
                    $roll->save();

                    if ($toRelease > FabricRollBalance::EPSILON) {
                        $this->recordAdjustmentWithMovement(
                            $roll,
                            'correction',
                            $quantityBefore,
                            $quantityBefore,
                            "Se libera la reserva de la ficha técnica {$opLabel} al descargar en corte",
                            reference: "cut_consumption::{$order->id}",
                            partialBefore: $partialBefore,
                            partialAfter: (float) $roll->partial_output,
                            actualOutputBefore: $actualOutputBefore,
                            actualOutputAfter: $actualOutputBefore,
                        );
                    }
                }

                if ($finish) {
                    $this->finishRoll($roll, "Último descargue del rollo en corte {$opLabel}.", $adjustment?->id, null, $order->id);
                }
            }

            // Consumos del corte de esta OP: estado final. Los de otros flujos no se tocan.
            // Por instancia para que el AuditObserver registre cada consumo eliminado.
            ProductionRollConsumption::where('production_order_id', $order->id)
                ->where('source', ProductionRollConsumption::SOURCE_CUT)
                ->get()
                ->each
                ->delete();

            foreach ($newByRoll as $rollId => $meters) {
                ProductionRollConsumption::create([
                    'production_order_id' => $order->id,
                    'fabric_roll_id' => $rollId,
                    'meters_consumed' => $meters,
                    'source' => ProductionRollConsumption::SOURCE_CUT,
                ]);
            }
        });
    }

    /**
     * Da el rollo por terminado: el saldo real que el sistema todavía tiene no existe
     * (faltante de metraje) y las reservas que quedaban sobre el rollo se liberan, porque
     * ya no hay tela que las respalde. El faltante no se imputa como consumo de ninguna orden.
     *
     * @throws ValidationException
     */
    protected function finishRoll(FabricRoll $roll, ?string $reason, ?int $sourceAdjustmentId, ?ManualRollProgramming $manualLine = null, ?int $productionOrderId = null): ?FabricRollLengthDifference
    {
        $remaining = FabricRollBalance::real($roll);
        $released = $this->releaseRollReservations($roll);
        $releasedTotal = FabricRollBalance::meters(array_sum(array_column($released, 'meters')));

        if ($remaining <= FabricRollBalance::EPSILON && $releasedTotal <= FabricRollBalance::EPSILON) {
            return null;
        }

        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => ['Indica el motivo para dar el rollo por terminado.'],
            ]);
        }

        $quantityBefore = (float) $roll->quantity;
        $partialBefore = (float) $roll->partial_output;
        $movement = null;

        if ($remaining > FabricRollBalance::EPSILON) {
            $movement = $this->createRollMovement(
                $roll,
                'salida',
                $remaining,
                "Faltante de metraje rollo #{$roll->roll_number} (rollo terminado). {$reason}",
            );
            $roll->quantity = FabricRollBalance::meters($quantityBefore - $remaining);
            $roll->length_review_status = FabricRoll::LENGTH_REVIEW_PENDING;
        }

        $roll->partial_output = FabricRollBalance::meters(max(0.0, $partialBefore - $releasedTotal));
        $roll->save();

        $releasedText = $released === []
            ? ''
            : ' Reservas liberadas: '.implode(', ', array_map(fn (array $r) => "{$r['code']} ({$r['meters']} m)", $released)).'.';

        $this->recordAdjustmentWithMovement(
            $roll,
            'correction',
            $quantityBefore,
            (float) $roll->quantity,
            'Rollo terminado'.($remaining > FabricRollBalance::EPSILON ? ": faltante de {$remaining} m" : '').". {$reason}{$releasedText}",
            reference: $manualLine ? "manual_programming::{$manualLine->id}" : ($productionOrderId ? "cut_consumption::{$productionOrderId}" : null),
            inventoryMovementId: $movement?->id,
            partialBefore: $partialBefore,
            partialAfter: (float) $roll->partial_output,
            actualOutputBefore: (float) $roll->actual_output,
            actualOutputAfter: (float) $roll->actual_output,
            quantity: $remaining > FabricRollBalance::EPSILON ? -$remaining : null,
        );

        if ($remaining <= FabricRollBalance::EPSILON) {
            return null;
        }

        return $this->recordLengthDifference(
            $roll,
            FabricRollLengthDifference::TYPE_SHORTAGE,
            $remaining,
            $quantityBefore,
            (float) $roll->quantity,
            $reason,
            $sourceAdjustmentId,
            $movement,
            manualLine: $manualLine,
            productionOrderId: $productionOrderId,
            releasedReservations: $released,
        );
    }

    /**
     * Libera todas las reservas pendientes sobre el rollo (manuales y de ficha técnica)
     * sin tocar lo programado. Devuelve el detalle de lo liberado.
     *
     * @return list<array{source:string, id:int, code:?string, meters:float}>
     */
    protected function releaseRollReservations(FabricRoll $roll): array
    {
        $released = [];

        foreach (ManualRollProgramming::where('fabric_roll_id', $roll->id)->lockForUpdate()->get() as $manualLine) {
            $pending = FabricRollBalance::meters($manualLine->remaining_meters);
            if ($pending <= FabricRollBalance::EPSILON) {
                continue;
            }

            $manualLine->released_meters = round((float) $manualLine->released_meters + $manualLine->remaining_meters, 6);
            $manualLine->save();

            $released[] = ['source' => 'manual', 'id' => (int) $manualLine->id, 'code' => $manualLine->manual_order_code, 'meters' => $pending];
        }

        $sheetLines = FabricRollProgrammingLine::with('technicalSheet.productionOrder:id,op_code')
            ->where('fabric_roll_id', $roll->id)
            ->lockForUpdate()
            ->get();

        foreach ($sheetLines as $sheetLine) {
            $pending = $sheetLine->pending_meters;
            if ($pending <= FabricRollBalance::EPSILON) {
                continue;
            }

            $sheetLine->released_meters = (float) $sheetLine->programmed_meters;
            $sheetLine->save();

            $released[] = [
                'source' => 'sheet',
                'id' => (int) $sheetLine->id,
                'code' => $sheetLine->technicalSheet?->productionOrder?->op_code,
                'meters' => $pending,
            ];
        }

        return $released;
    }

    /**
     * Revierte (total o parcialmente) el sobrante que registró un descargue, cuando una
     * corrección baja su consumo: ese metraje de más no existió.
     */
    protected function reverseSurplusOfDischarge(FabricRoll $roll, FabricRollAdjustment $discharge, float $returnedMeters, int $correctionId, ManualRollProgramming $line): void
    {
        $adjustmentIds = $discharge->corrections()->pluck('id')->push($discharge->id)->all();

        $surpluses = FabricRollLengthDifference::where('fabric_roll_id', $roll->id)
            ->where('type', FabricRollLengthDifference::TYPE_SURPLUS)
            ->whereIn('source_adjustment_id', $adjustmentIds)
            ->orderByDesc('id')
            ->get();

        foreach ($surpluses as $surplus) {
            if ($returnedMeters <= FabricRollBalance::EPSILON) {
                return;
            }

            $alreadyReversed = (float) FabricRollLengthDifference::where('reverses_id', $surplus->id)->sum('meters');
            $open = FabricRollBalance::meters((float) $surplus->meters - $alreadyReversed);
            $toReverse = FabricRollBalance::meters(min($open, $returnedMeters));
            if ($toReverse <= FabricRollBalance::EPSILON) {
                continue;
            }

            $quantityBefore = (float) $roll->quantity;
            $movement = $this->createRollMovement($roll, 'salida', $toReverse, "Reversión de sobrante de metraje rollo #{$roll->roll_number} (corrección de descargue #{$discharge->id})");
            $roll->quantity = FabricRollBalance::meters($quantityBefore - $toReverse);
            $roll->length_review_status = FabricRoll::LENGTH_REVIEW_PENDING;
            $roll->save();

            $this->recordLengthDifference(
                $roll,
                FabricRollLengthDifference::TYPE_SHORTAGE,
                $toReverse,
                $quantityBefore,
                (float) $roll->quantity,
                "Corrección del descargue #{$discharge->id}: se consumió menos, el sobrante registrado no existió.",
                $correctionId,
                $movement,
                manualLine: $line,
                productionOrderId: $line->production_order_id,
                reversesId: $surplus->id,
            );

            $returnedMeters = FabricRollBalance::meters($returnedMeters - $toReverse);
        }
    }

    /**
     * @param  list<array{source:string, id:int, code:?string, meters:float}>  $releasedReservations
     */
    protected function recordLengthDifference(
        FabricRoll $roll,
        string $type,
        float $meters,
        float $quantityBefore,
        float $quantityAfter,
        string $reason,
        ?int $sourceAdjustmentId,
        ?InventoryMovement $movement,
        ?ManualRollProgramming $manualLine = null,
        ?int $productionOrderId = null,
        array $releasedReservations = [],
        ?int $reversesId = null,
    ): FabricRollLengthDifference {
        return FabricRollLengthDifference::create([
            'fabric_roll_id' => $roll->id,
            'type' => $type,
            'meters' => FabricRollBalance::meters($meters),
            'quantity_before' => FabricRollBalance::meters($quantityBefore),
            'quantity_after' => FabricRollBalance::meters($quantityAfter),
            'reason' => $reason,
            'source_adjustment_id' => $sourceAdjustmentId,
            'manual_roll_programming_id' => $manualLine?->id,
            'production_order_id' => $productionOrderId,
            'inventory_movement_id' => $movement?->id,
            'released_reservations' => $releasedReservations ?: null,
            'reverses_id' => $reversesId,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    /**
     * Revisión de un supervisor sobre una diferencia de metraje. No revierte nada: si hubo
     * un error, se corrige el descargue (lo que genera su propio evento). Cuando el rollo ya
     * no tiene diferencias sin revisar, su estado pasa a revisado.
     *
     * @throws ValidationException
     */
    public function reviewLengthDifference(FabricRollLengthDifference $difference, ?string $notes): FabricRollLengthDifference
    {
        return DB::transaction(function () use ($difference, $notes) {
            $difference = FabricRollLengthDifference::lockForUpdate()->findOrFail($difference->id);

            if ($difference->reviewed_at !== null) {
                throw ValidationException::withMessages([
                    'general' => ['Esta diferencia de metraje ya fue revisada.'],
                ]);
            }

            $difference->update([
                'reviewed_by_user_id' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $this->cleanReason($notes),
            ]);

            $pending = FabricRollLengthDifference::where('fabric_roll_id', $difference->fabric_roll_id)
                ->whereNull('reviewed_at')
                ->exists();

            if (! $pending) {
                FabricRoll::whereKey($difference->fabric_roll_id)
                    ->update(['length_review_status' => FabricRoll::LENGTH_REVIEW_REVIEWED]);

                app(AuditLogService::class)->logBulk(
                    FabricRoll::class,
                    'updated',
                    [$difference->fabric_roll_id],
                    ['length_review_status' => FabricRoll::LENGTH_REVIEW_REVIEWED],
                    'Diferencias de metraje del rollo revisadas',
                );
            }

            return $difference->fresh(['roll', 'createdBy', 'reviewedBy']);
        });
    }

    /**
     * Ajusta la reserva del rollo al valor comprometido por sus programaciones, dejando el
     * ajuste en el historial (auditoría de saldos).
     */
    public function recalibrateReservation(FabricRoll $fabricRoll, float $expectedPartial, string $reason): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $expectedPartial, $reason) {
            $roll = FabricRoll::lockForUpdate()->findOrFail($fabricRoll->id);
            $partialBefore = (float) $roll->partial_output;
            $roll->partial_output = FabricRollBalance::meters(max(0.0, $expectedPartial));
            $roll->save();

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                $reason,
                partialBefore: $partialBefore,
                partialAfter: (float) $roll->partial_output,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            return $roll;
        });
    }

    /**
     * Revierte el desvío que dejó un descargue corregido varias veces con el código anterior
     * (cada corrección se aplicaba contra la cantidad original). $excess > 0: se registró
     * de más en consumo del rollo, de la línea y salida de kardex; se devuelve al rollo.
     */
    public function repairDuplicatedCorrection(FabricRollAdjustment $discharge, float $excess): void
    {
        DB::transaction(function () use ($discharge, $excess) {
            $discharge = FabricRollAdjustment::with('corrections')->lockForUpdate()->findOrFail($discharge->id);
            $line = ManualRollProgramming::withTrashed()->lockForUpdate()
                ->findOrFail((int) str_replace('manual_programming::', '', (string) $discharge->reference));
            $roll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($line->fabric_roll_id);

            $excess = FabricRollBalance::meters($excess);
            $quantityBefore = (float) $roll->quantity;
            $partialBefore = (float) $roll->partial_output;
            $actualOutputBefore = (float) $roll->actual_output;

            // El kardex solo quedó desviado si el descargue o sus correcciones movieron inventario.
            $hadKardex = $discharge->inventory_movement_id !== null
                || $discharge->corrections->contains(fn (FabricRollAdjustment $c) => $c->inventory_movement_id !== null);
            $movement = $hadKardex
                ? $this->createRollMovement(
                    $roll,
                    $excess > 0 ? 'entrada' : 'salida',
                    abs($excess),
                    "Auditoría de saldos: desvío por corrección duplicada del descargue #{$discharge->id} rollo #{$roll->roll_number}",
                )
                : null;

            $reservedBefore = $line->remaining_meters;
            $line->consumed_meters = round(max(0.0, (float) $line->consumed_meters - $excess), 6);
            $line->save();
            $reservedAfter = $line->remaining_meters;

            $roll->actual_output = FabricRollBalance::meters(max(0.0, $actualOutputBefore - $excess));
            $roll->partial_output = FabricRollBalance::meters(max(0.0, $partialBefore + ($reservedAfter - $reservedBefore)));
            $roll->save();

            $repair = $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                $quantityBefore,
                (float) $roll->quantity,
                "Auditoría de saldos: se revierte un desvío de {$excess} m del descargue #{$discharge->id} (correcciones aplicadas contra la cantidad original).",
                reference: $discharge->reference,
                inventoryMovementId: $movement?->id,
                partialBefore: $partialBefore,
                partialAfter: (float) $roll->partial_output,
                actualOutputBefore: $actualOutputBefore,
                actualOutputAfter: (float) $roll->actual_output,
                quantity: -$excess,
                correctsAdjustmentId: $discharge->id,
            );

            $this->recordRollConsumption($roll, -$excess, $line->production_order_id, ProductionRollConsumption::SOURCE_MANUAL, $repair->id);
        });
    }

    protected function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : null;
    }

    /**
     * Centraliza la creación del registro de auditoría de ajuste de rollo (fabric_roll_adjustments),
     * enlazando siempre el InventoryMovement creado en el mismo flujo (nunca por búsqueda de texto).
     * $quantity es la cantidad vigente del evento (descargue, entrada, corrección).
     */
    protected function recordAdjustmentWithMovement(
        FabricRoll $roll,
        string $type,
        float $quantityBefore,
        float $quantityAfter,
        ?string $reason,
        ?string $reference = null,
        ?int $inventoryMovementId = null,
        ?float $partialBefore = null,
        ?float $partialAfter = null,
        ?float $actualOutputBefore = null,
        ?float $actualOutputAfter = null,
        ?float $quantity = null,
        ?int $correctsAdjustmentId = null,
    ): FabricRollAdjustment {
        return FabricRollAdjustment::create([
            'fabric_roll_id' => $roll->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'partial_before' => $partialBefore,
            'partial_after' => $partialAfter,
            'actual_output_before' => $actualOutputBefore,
            'actual_output_after' => $actualOutputAfter,
            'reason' => $reason ?? '',
            'reference' => $reference,
            'corrects_adjustment_id' => $correctsAdjustmentId,
            'inventory_movement_id' => $inventoryMovementId,
        ]);
    }

    /**
     * Trazabilidad rollo/orden del consumo. No depende de tener un ítem vinculado: se
     * registra siempre, aunque el movimiento de kardex todavía no exista.
     */
    protected function recordRollConsumption(FabricRoll $roll, float $meters, ?int $productionOrderId, string $source, ?int $adjustmentId): ProductionRollConsumption
    {
        return ProductionRollConsumption::create([
            'production_order_id' => $productionOrderId,
            'fabric_roll_id' => $roll->id,
            'meters_consumed' => $meters,
            'source' => $source,
            'fabric_roll_adjustment_id' => $adjustmentId,
        ]);
    }

    /**
     * Crea un rollo desde recepción de factura.
     * Persiste el precio unitario ($pdi->price) en price_per_meter para seguimiento y análisis de costos.
     * Este precio se puede actualizar después si el rollo necesita corrección o si no tiene compra vinculada.
     */
    public function storeFromReception(array $data): FabricRoll
    {
        $this->ensureInvoiceNotYetReceptionApproved((int) $data['purchase_document_item_id']);

        $pdi = PurchaseDocumentItem::findOrFail($data['purchase_document_item_id']);
        $data['item_id'] = $pdi->item_id;

        // Resolver color desde el item si no viene en el payload
        if (empty($data['internal_color_name']) && $pdi->item_id) {
            $item = Item::with('color')->find($pdi->item_id);
            if ($item && $item->color) {
                $data['internal_color_name'] = $item->color->name;
            }
        }

        // Guardar price_per_meter desde el precio unitario de la factura de compra
        // si no viene en el payload. Este precio persiste en BD para auditoría y análisis.
        if (empty($data['price_per_meter']) && $pdi->price) {
            $data['price_per_meter'] = (float) $pdi->price;
        }

        $document = $pdi->document;
        $receptionAlreadyApproved = $document !== null && $document->status === 'approved';
        $postApprovalReason = trim((string) ($data['post_approval_reason'] ?? ''));
        unset($data['post_approval_reason']);

        if ($receptionAlreadyApproved) {
            $this->ensureRollAdditionAfterApprovalAllowed($pdi, (float) ($data['quantity'] ?? 0), $postApprovalReason);
        }

        // El estado de aprobación nunca lo decide el cliente: solo la aprobación de la recepción lo cambia.
        $data['approval_status'] = $receptionAlreadyApproved ? FabricRoll::APPROVAL_APPROVED : FabricRoll::APPROVAL_PENDING;

        return DB::transaction(function () use ($data, $document, $receptionAlreadyApproved, $postApprovalReason) {
            $roll = $this->create($data);

            if ($receptionAlreadyApproved) {
                $this->recordRollReceptionEvent(
                    $document,
                    PurchaseDocumentReceptionEvent::TYPE_ROLL_ADDED_AFTER_APPROVAL,
                    $postApprovalReason,
                    'Rollo '.$roll->roll_number.': '.number_format((float) $roll->quantity, 2, ',', '.').' mts'
                );

                $documentNumber = trim((string) ($document->document_number ?? '')) !== ''
                    ? trim((string) $document->document_number)
                    : 'Factura #'.$document->id;
                $responsibleUserId = Auth::id() ?? $document->approved_by_user_id ?? $document->created_by_user_id;
                if (empty($responsibleUserId)) {
                    throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción de rollos.');
                }

                $this->createReceptionInventoryMovement($roll, $document, $documentNumber, (int) $responsibleUserId);
            }

            return $roll;
        });
    }

    /**
     * No permite registrar rollos si la factura ya fue marcada como recepcionada (aprobada).
     *
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApproved(int $purchaseDocumentItemId): void
    {
        $item = PurchaseDocumentItem::with('document')->find($purchaseDocumentItemId);
        if (! $item || ! $item->document) {
            return;
        }
        // Recepción ya aprobada: solo quien tiene el permiso exclusivo añade más rollos (entran directo al inventario).
        if ($item->document->status === 'approved' && ! Auth::user()?->can(self::PERMISSION_ADD_ROLLS_AFTER_APPROVAL)) {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir más rollos: la factura ya está recepcionada (aprobada). Solo quien tenga el permiso "agregar rollos recepcion aprobada" puede hacerlo.'],
            ]);
        }

        if ($item->document->status === 'rejected') {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir rollos: la recepción de la factura fue rechazada.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($item->document)) {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir rollos: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
            ]);
        }
    }

    /**
     * Añadir un rollo con la recepción ya aprobada es una excepción: exige motivo y no puede sacar la línea de la
     * tolerancia (±30 mts) sin aprobación. Se admite si la deja dentro, o si acerca lo recibido a lo facturado
     * (p. ej. completar un faltante ya aprobado). Una entrega adicional real va en una factura nueva.
     *
     * @throws ValidationException
     */
    protected function ensureRollAdditionAfterApprovalAllowed(PurchaseDocumentItem $line, float $quantity, string $reason): void
    {
        if ($reason === '') {
            throw ValidationException::withMessages([
                'post_approval_reason' => ['La recepción ya está aprobada: indica el motivo para añadir este rollo.'],
            ]);
        }

        $ordered = (float) $line->quantity;
        $received = $this->sumRollQuantityForPurchaseDocumentItem($line->id);
        $differenceBefore = abs($ordered - $received);
        $differenceAfter = abs($ordered - ($received + $quantity));

        if ($differenceAfter > self::TOLERANCE_QUANTITY_METERS && $differenceAfter >= $differenceBefore) {
            throw ValidationException::withMessages([
                'quantity' => [sprintf(
                    'Con este rollo la línea quedaría con %s mts recibidos frente a %s facturados (diferencia de %s mts, supera la tolerancia de %d mts). Con la recepción aprobada no se puede superar la tolerancia: registra la entrega adicional en una factura nueva.',
                    number_format($received + $quantity, 2, ',', '.'),
                    number_format($ordered, 2, ',', '.'),
                    number_format($differenceAfter, 2, ',', '.'),
                    self::TOLERANCE_QUANTITY_METERS
                )],
            ]);
        }
    }

    /** Suma de metros recibidos para un ítem de documento (línea de factura). */
    protected function sumRollQuantityForPurchaseDocumentItem(int $purchaseDocumentItemId): float
    {
        // Lo recibido, no el metraje vigente: un sobrante o faltante detectado al descargar
        // no cambia lo que llegó contra la factura.
        return (float) FabricRoll::where('purchase_document_item_id', $purchaseDocumentItemId)
            ->sum(DB::raw('COALESCE(received_quantity, quantity)'));
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
            'creditNoteLinks.creditNote',
        ])
            ->where('purchase_document_item_id', $itemId)
            ->orderBy('entry_date')
            ->get();
    }

    public function export(array $fabricItemIds = []): Collection
    {
        $query = FabricRoll::with([
            'item.color',
            'item.fabricName',
            'purchaseDocumentItem.item.color',
            'purchaseDocumentItem.item.fabricName',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (! empty($fabricItemIds)) {
            $query->whereHas('purchaseDocumentItem', function ($builder) use ($fabricItemIds) {
                $builder->whereIn('item_id', $fabricItemIds);
            });
        }

        return $query
            ->where('approval_status', FabricRoll::APPROVAL_APPROVED)
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    /**
     * Retorna rollos con datos de tela/proveedor aplanados vía JOINs.
     * Evita eager-loading masivo: carga solo columnas necesarias en una sola query.
     */
    public function inventoryWithFilters(array $filters = [], int $perPage = 100, string $sortBy = 'entry_date', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $allowedSorts = [
            'roll_number' => DB::raw("CAST(REGEXP_REPLACE(fr.roll_number, '[^0-9]', '') AS UNSIGNED)"),
            'location' => 'fr.location',
            'fabric' => DB::raw("COALESCE(i.name, pdi.item_name, 'Tela')"),
            'color' => DB::raw('COALESCE(fr.internal_color_name, pdi.supplier_color)'),
            'width' => 'fr.roll_width',
            'quantity' => 'fr.quantity',
            'partial_output' => 'fr.partial_output',
            'actual_output' => 'fr.actual_output',
            'entry_date' => 'fr.entry_date',
            'provider' => DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor')"),
        ];

        $sortColumn = $allowedSorts[$sortBy] ?? 'fr.entry_date';
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('fabric_rolls as fr')
            ->select([
                'fr.id',
                'fr.roll_number',
                'fr.location',
                'fr.quantity',
                'fr.received_quantity',
                'fr.length_review_status',
                'fr.roll_width',
                DB::raw(FabricRollBalance::sqlFree('fr').' as partial_availability_value'),
                'fr.partial_output',
                'fr.actual_output',
                'fr.internal_color_name',
                DB::raw('COALESCE(col_fi.hexadecimal, col_i.hexadecimal) as item_color_hex'),
                DB::raw('COALESCE(col_fi.name, col_i.name) as item_color_name'),
                DB::raw("COALESCE(fi.name, i.name, pdi.item_name, fr.description, 'Tela') as fabric"),
                'pdi.id as purchase_document_item_id',
                DB::raw('COALESCE(fr.item_id, pdi.item_id) as fabric_item_id'),
                'pdi.supplier_color',
                'pdi.supplier_code',
                DB::raw('COALESCE(fr.price_per_meter, pdi.price) as price_per_meter'),
                DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor') as provider"),
                DB::raw("COALESCE(pd.supplier_order_number, pd.document_number, 'N/A') as order_number"),
                'pd.created_at as doc_created_at',
                'pd.id as invoice_id',
                'pd.document_number as invoice_number',
                'u.name as creator_name',
                'fr.tolerance_status',
                'fr.tolerance_reason',
                // Mismo criterio que hasDischarges(): con descargues el rollo queda bloqueado.
                DB::raw(
                    "(EXISTS (SELECT 1 FROM fabric_roll_adjustments fra WHERE fra.fabric_roll_id = fr.id AND fra.type = 'salida')"
                    .' OR EXISTS (SELECT 1 FROM production_roll_consumptions prc WHERE prc.fabric_roll_id = fr.id)'
                    .' OR EXISTS (SELECT 1 FROM fabric_roll_length_differences frd WHERE frd.fabric_roll_id = fr.id)) as has_discharges'
                ),
                DB::raw(
                    '(SELECT pdn.document_number FROM fabric_roll_credit_note_links frl '
                    .'JOIN purchase_documents pdn ON pdn.id = frl.credit_note_document_id '
                    .'WHERE frl.fabric_roll_id = fr.id AND pdn.deleted_at IS NULL '
                    .'ORDER BY frl.id DESC LIMIT 1) as credit_note_number'
                ),
            ])
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->leftJoin('items as fi', 'fi.id', '=', 'fr.item_id')
            ->leftJoin('colors as col_fi', 'col_fi.id', '=', 'fi.color_id')
            ->leftJoin('items as i', 'i.id', '=', 'pdi.item_id')
            ->leftJoin('colors as col_i', 'col_i.id', '=', 'i.color_id')
            ->leftJoin('purchase_documents as pd', 'pd.id', '=', 'pdi.purchase_document_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'pd.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'pd.created_by_user_id')
            ->whereNull('fr.deleted_at');

        if (! empty($filters['restricted'])) {
            // Modo "Restringidos": rollos rechazados en recepción cuya nota crédito aún
            // no se aprobó (no están en stock disponible: approval_status = not_approved).
            $this->applyRestrictedRollScope($query, 'fr');
        } else {
            $query->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fr.roll_number', 'like', "%{$search}%")
                    ->orWhere('fr.location', 'like', "%{$search}%")
                    ->orWhere('fi.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('pdi.item_name', 'like', "%{$search}%")
                    ->orWhere('fr.description', 'like', "%{$search}%")
                    ->orWhere('fr.internal_color_name', 'like', "%{$search}%")
                    ->orWhere('pdi.supplier_color', 'like', "%{$search}%")
                    ->orWhere('col_fi.name', 'like', "%{$search}%")
                    ->orWhere('col_i.name', 'like', "%{$search}%")
                    ->orWhere('c.full_name', 'like', "%{$search}%")
                    ->orWhere('pd.provider_name', 'like', "%{$search}%")
                    ->orWhere('pd.supplier_order_number', 'like', "%{$search}%")
                    ->orWhere('pd.document_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['color'])) {
            $query->where('fr.internal_color_name', $filters['color']);
        }

        if (! empty($filters['location'])) {
            $query->where('fr.location', $filters['location']);
        }

        if (! empty($filters['with_output'])) {
            $query->where(function ($q) {
                $q->where('fr.partial_output', '>', 0)
                    ->orWhere('fr.actual_output', '>', 0);
            });
        }

        if (! empty($filters['fabric_item_ids'])) {
            $query->whereIn(DB::raw('COALESCE(fr.item_id, pdi.item_id)'), (array) $filters['fabric_item_ids']);
        }

        if (! empty($filters['no_item'])) {
            $query->whereNull('fr.item_id')->whereNull('pdi.item_id');
        }

        return $query->orderBy($sortColumn, $sortDirection)->paginate($perPage);
    }

    /**
     * Restringe una consulta a rollos restringidos: rechazados en recepción y sin resolver
     * (la nota crédito enlazada todavía no se aprobó, por lo que no se han dado de baja).
     */
    public function applyRestrictedRollScope($query, string $alias = 'fabric_rolls')
    {
        return $query
            ->where("{$alias}.tolerance_status", FabricRoll::TOLERANCE_REJECTED)
            ->whereNull("{$alias}.resolved_at");
    }

    public function available(): Collection
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->whereColumn('quantity', '>', 'actual_output')
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    /**
     * Registra una ronda de recepción para líneas generales (no rollos de tela) de
     * una factura de compra: crea el movimiento de inventario por línea en la
     * bodega indicada, sin cambiar el estado de la factura. Se puede llamar varias
     * veces sobre la misma factura (recepción parcial en varias rondas).
     *
     * @param  array<int, array{purchase_document_item_id: int, warehouse_id: int, quantity: float, over_received_reason?: string|null}>  $lines
     */
    public function registerGeneralReception($invoiceId, array $lines): PurchaseDocument
    {
        $invoice = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);
        $this->ensureReceptionNotApproved($invoice, 'La recepción de esta factura ya fue aprobada; no se pueden registrar más rondas.');

        return DB::transaction(function () use ($invoice, $lines) {
            $lockedInvoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $lineIds = collect($lines)->pluck('purchase_document_item_id')->unique()->all();

            $lockedLines = PurchaseDocumentItem::where('purchase_document_id', $lockedInvoice->id)
                ->whereIn('id', $lineIds)
                ->with('item')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lockedLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Ninguna de las líneas enviadas pertenece a esta factura.'],
                ]);
            }

            $documentNumber = trim((string) ($lockedInvoice->document_number ?? '')) !== ''
                ? trim((string) $lockedInvoice->document_number)
                : 'Factura #'.$lockedInvoice->id;
            $responsibleUserId = Auth::id() ?? $lockedInvoice->approved_by_user_id ?? $lockedInvoice->created_by_user_id;

            if (empty($responsibleUserId)) {
                throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción.');
            }

            foreach ($lines as $payloadLine) {
                $line = $lockedLines->get($payloadLine['purchase_document_item_id']);
                if (! $line) {
                    continue;
                }

                $item = $line->item;
                if (! $item || ! $item->tracks_inventory) {
                    continue;
                }

                $quantity = (float) $payloadLine['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $previouslyReceived = (float) PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->sum('quantity');
                $reason = $payloadLine['over_received_reason'] ?? null;

                if ($previouslyReceived + $quantity > (float) $line->quantity && empty(trim((string) $reason))) {
                    throw ValidationException::withMessages([
                        "lines.{$line->id}.over_received_reason" => ['Esta línea supera lo facturado — indica un motivo para la sobre-recepción.'],
                    ]);
                }

                $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, (int) $payloadLine['warehouse_id']);

                $reception = PurchaseDocumentItemReception::create([
                    'purchase_document_item_id' => $line->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'unit_price' => $line->price,
                    'over_received_reason' => $reason,
                    'received_by_user_id' => (int) $responsibleUserId,
                    'received_at' => now(),
                ]);

                $movement = $this->movementService->createMovement([
                    'document_number' => $this->movementNumberWithSuffix($documentNumber, ' [R'.$reception->id.']'),
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => $quantity,
                    'unit_price' => (float) $line->price,
                    'date' => now()->toDateString(),
                    'source_type' => PurchaseDocument::class,
                    'source_id' => $lockedInvoice->id,
                    'source_reference' => 'reception-'.$reception->id,
                    'notes' => 'Recepción de "'.($item->name ?? $line->item_name).'" de factura '.$documentNumber,
                    'created_by_user_id' => (int) $responsibleUserId,
                ]);

                $reception->update(['inventory_movement_id' => $movement->id]);
            }

            return $lockedInvoice->fresh();
        });
    }

    /**
     * Corrige una ronda ya registrada sin alterar el kardex: revierte el movimiento
     * original y crea uno nuevo con la cantidad y bodega corregidas.
     */
    public function updateGeneralReception($invoiceId, int $receptionId, array $data): PurchaseDocumentItemReception
    {
        return DB::transaction(function () use ($invoiceId, $receptionId, $data) {
            $invoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoiceId);
            $this->ensureInvoiceReceptionAllowed($invoice);
            $this->ensureReceptionNotApproved(
                $invoice,
                'La recepción ya fue aprobada: la corrección se hace con un ajuste de inventario, no editando la recepción.'
            );

            $reception = PurchaseDocumentItemReception::query()
                ->whereHas('purchaseDocumentItem', fn ($query) => $query->where('purchase_document_id', $invoice->id))
                ->lockForUpdate()
                ->findOrFail($receptionId);

            $line = PurchaseDocumentItem::query()
                ->with('item')
                ->lockForUpdate()
                ->findOrFail($reception->purchase_document_item_id);
            $item = $line->item;

            if (! $item || ! $item->tracks_inventory) {
                throw ValidationException::withMessages([
                    'reception' => ['Solo se pueden corregir recepciones de ítems que controlan inventario.'],
                ]);
            }

            $quantity = (float) $data['quantity'];
            $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, (int) $data['warehouse_id']);
            $reason = trim((string) ($data['over_received_reason'] ?? '')) ?: null;
            $otherReceived = (float) PurchaseDocumentItemReception::query()
                ->where('purchase_document_item_id', $line->id)
                ->where('id', '!=', $reception->id)
                ->sum('quantity');

            if ($otherReceived + $quantity > (float) $line->quantity && ! $reason) {
                throw ValidationException::withMessages([
                    'over_received_reason' => ['Esta línea supera lo facturado — indica un motivo para la sobre-recepción.'],
                ]);
            }

            $originalMovement = InventoryMovement::query()
                ->lockForUpdate()
                ->find($reception->inventory_movement_id);

            if (! $originalMovement
                || $originalMovement->type !== 'entrada'
                || (int) $originalMovement->item_id !== (int) $item->id
                || (int) $originalMovement->warehouse_id !== (int) $reception->warehouse_id) {
                throw ValidationException::withMessages([
                    'reception' => ['No se puede corregir esta recepción porque no tiene un movimiento de inventario válido asociado.'],
                ]);
            }

            $this->movementService->reverseMovement(
                $originalMovement,
                'Corrección de recepción '.$reception->id.' de factura '.$invoice->document_number
            );

            $unitPrice = (float) ($reception->unit_price ?? $line->price);
            $documentNumber = trim((string) ($invoice->document_number ?? '')) ?: 'Factura #'.$invoice->id;
            // El número es único en inventory_movements: cada corrección lleva su propio sufijo (el original es "[R<id>]").
            $correctionNumber = InventoryMovement::query()
                ->where('source_type', PurchaseDocument::class)
                ->where('source_id', $invoice->id)
                ->where('source_reference', 'reception-'.$reception->id)
                ->count();
            $correctedMovement = $this->movementService->createMovement([
                'document_number' => $this->movementNumberWithSuffix($documentNumber, ' [R'.$reception->id.'-C'.max(1, $correctionNumber).']'),
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'type' => 'entrada',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'date' => now()->toDateString(),
                'source_type' => PurchaseDocument::class,
                'source_id' => $invoice->id,
                'source_reference' => 'reception-'.$reception->id,
                'notes' => 'Recepción corregida de "'.($item->name ?? $line->item_name).'" de factura '.$documentNumber,
                'created_by_user_id' => Auth::id() ?? $reception->received_by_user_id,
            ]);

            $reception->update([
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'over_received_reason' => $reason,
                'inventory_movement_id' => $correctedMovement->id,
            ]);

            return $reception->fresh(['warehouse', 'purchaseDocumentItem.item', 'receivedBy']);
        });
    }

    /** inventory_movements.document_number es único y de 50 caracteres: se recorta la base, nunca el sufijo que lo distingue. */
    protected function movementNumberWithSuffix(string $base, string $suffix): string
    {
        return mb_substr($base, 0, 50 - mb_strlen($suffix)).$suffix;
    }

    protected function createReceptionInventoryMovement(FabricRoll $roll, PurchaseDocument $invoice, string $documentNumber, int $responsibleUserId): void
    {
        $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
        if (! $item) {
            return;
        }

        $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
        $unitPrice = $roll->price_per_meter !== null
            ? (float) $roll->price_per_meter
            : (float) ($roll->purchaseDocumentItem?->price ?? 0);
        $movementDocumentNumber = $this->movementNumberWithSuffix($documentNumber, ' ['.$roll->roll_number.']');

        $this->movementService->createMovement([
            'document_number' => $movementDocumentNumber,
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'type' => 'entrada',
            'quantity' => (float) $roll->quantity,
            'unit_price' => $unitPrice,
            'date' => $roll->entry_date?->toDateString() ?? now()->toDateString(),
            'source_type' => PurchaseDocument::class,
            'source_id' => $invoice->id,
            'source_reference' => $roll->roll_number,
            'notes' => 'Recepción de rollo '.$roll->roll_number.' de factura '.$documentNumber,
            'created_by_user_id' => $responsibleUserId,
        ]);
    }

    public function approveReception($invoiceId)
    {
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);
        $this->ensureReceptionNotApproved($invoice);
        $this->ensureReceptionDifferencesApproved($invoice);

        $approvedInvoice = DB::transaction(function () use ($invoice) {
            $lockedInvoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $this->ensureNoRollsPendingReview($lockedInvoice);

            $pendingRolls = FabricRoll::with([
                'item',
                'purchaseDocumentItem.item',
            ])
                ->whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                    $q->where('purchase_document_id', $invoice->id);
                })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING)
                ->lockForUpdate()
                ->get();

            $lockedInvoice->update(['status' => 'approved', 'received_at' => $lockedInvoice->received_at ?? now()]);

            if ($pendingRolls->isNotEmpty()) {
                FabricRoll::whereIn('id', $pendingRolls->pluck('id'))
                    ->update(['approval_status' => FabricRoll::APPROVAL_APPROVED]);

                app(AuditLogService::class)->logBulk(
                    FabricRoll::class,
                    'updated',
                    $pendingRolls->pluck('id')->all(),
                    ['approval_status' => FabricRoll::APPROVAL_APPROVED, 'purchase_document_id' => $lockedInvoice->id],
                    'Aprobación de recepción de rollos de la factura',
                );

                $existingMovementReferences = DB::table('inventory_movements')
                    ->where('source_type', PurchaseDocument::class)
                    ->where('source_id', $lockedInvoice->id)
                    ->whereIn('source_reference', $pendingRolls->pluck('roll_number'))
                    ->pluck('source_reference')
                    ->all();

                $existingMovementReferences = array_fill_keys($existingMovementReferences, true);
                $documentNumber = trim((string) ($lockedInvoice->document_number ?? '')) !== ''
                    ? trim((string) $lockedInvoice->document_number)
                    : 'Factura #'.$lockedInvoice->id;
                $responsibleUserId = Auth::id() ?? $lockedInvoice->approved_by_user_id ?? $lockedInvoice->created_by_user_id;

                if (empty($responsibleUserId)) {
                    throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción de rollos.');
                }

                foreach ($pendingRolls as $roll) {
                    if (isset($existingMovementReferences[$roll->roll_number])) {
                        continue;
                    }

                    $this->createReceptionInventoryMovement($roll, $lockedInvoice, $documentNumber, (int) $responsibleUserId);
                }
            }

            return $lockedInvoice->fresh();
        });

        // La contabilización va FUERA de la transacción de recepción: la recepción física/inventario no se
        // bloquea (ni se revierte) por falta de configuración contable; el fallo vuelve como advertencia.
        $accountingWarning = $this->postApprovedInvoiceAccounting($approvedInvoice);
        if ($accountingWarning !== null) {
            $approvedInvoice->setAttribute('accounting_warning', $accountingWarning);
        }

        return $approvedInvoice;
    }

    /**
     * Contabiliza la factura de tela recién recepcionada (idempotente: si ya tiene asiento no hace nada).
     * Devuelve un mensaje de advertencia si no se pudo contabilizar, o null si quedó contabilizada.
     */
    protected function postApprovedInvoiceAccounting(PurchaseDocument $invoice): ?string
    {
        try {
            $this->accountingPostingService->postPurchaseInvoiceIfMissing($invoice);

            return null;
        } catch (\Throwable $e) {
            Log::warning('FabricRollService: recepción aprobada pero la factura de tela no se pudo contabilizar', [
                'invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
                'error' => $e->getMessage(),
            ]);

            return 'La recepción se aprobó, pero la factura no se pudo contabilizar: '.$e->getMessage()
                .' Complete la configuración contable (cuentas del ítem, IVA y cuentas por pagar); la factura se contabilizará automáticamente al crear una nota crédito sobre ella.';
        }
    }

    /**
     * Recalcula el estado de tolerancia de los rollos de una línea de factura de tela.
     * Si la línea queda fuera de ±TOLERANCE_QUANTITY_METERS (recibido incluye rollos rechazados),
     * los rollos 'none' pasan a 'pending_review'; si vuelve a tolerancia, los 'pending_review' vuelven a 'none'.
     * Nunca toca 'approved_with_difference' ni 'rejected'. Solo aplica mientras la factura espera recepción.
     */
    public function syncToleranceStatusForLine(int $purchaseDocumentItemId): void
    {
        $line = PurchaseDocumentItem::with(['document', 'item.categorySubGroup'])->find($purchaseDocumentItemId);
        if (! $line || ! $line->document || $line->document->status !== 'pending_receipt') {
            return;
        }
        if (! $this->isFabricDocumentItem($line)) {
            return;
        }

        $ordered = (float) $line->quantity;
        $quantityOutside = $ordered > 0
            && abs($ordered - $this->sumRollQuantityForPurchaseDocumentItem($line->id)) > self::TOLERANCE_QUANTITY_METERS;
        $referenceWidth = (float) $line->order_width;

        $rolls = FabricRoll::where('purchase_document_item_id', $line->id)
            ->whereIn('tolerance_status', [FabricRoll::TOLERANCE_NONE, FabricRoll::TOLERANCE_PENDING_REVIEW])
            ->get(['id', 'roll_width', 'tolerance_status']);

        $toReview = [];
        $toClear = [];
        foreach ($rolls as $roll) {
            $needsReview = $quantityOutside || $this->isRollWidthOutsideTolerance((float) $roll->roll_width, $referenceWidth);
            if ($needsReview && $roll->tolerance_status === FabricRoll::TOLERANCE_NONE) {
                $toReview[] = $roll->id;
            } elseif (! $needsReview && $roll->tolerance_status === FabricRoll::TOLERANCE_PENDING_REVIEW) {
                $toClear[] = $roll->id;
            }
        }

        if ($toReview) {
            FabricRoll::whereIn('id', $toReview)->update(['tolerance_status' => FabricRoll::TOLERANCE_PENDING_REVIEW]);
            app(AuditLogService::class)->logBulk(
                FabricRoll::class,
                'updated',
                $toReview,
                ['tolerance_status' => FabricRoll::TOLERANCE_PENDING_REVIEW],
                'Línea de factura fuera de tolerancia',
            );
        }
        if ($toClear) {
            FabricRoll::whereIn('id', $toClear)->update(['tolerance_status' => FabricRoll::TOLERANCE_NONE]);
            app(AuditLogService::class)->logBulk(
                FabricRoll::class,
                'updated',
                $toClear,
                ['tolerance_status' => FabricRoll::TOLERANCE_NONE],
                'Línea de factura vuelve a tolerancia',
            );
        }
    }

    /**
     * Tolerancia de ancho por rollo (asimétrica): sin límite hacia arriba, máx. TOLERANCE_WIDTH_BELOW_METERS hacia abajo.
     * Sin ancho de referencia o sin ancho registrado no hay nada que comparar.
     */
    protected function isRollWidthOutsideTolerance(float $rollWidth, float $referenceWidth): bool
    {
        if ($referenceWidth <= 0 || $rollWidth <= 0) {
            return false;
        }

        $difference = round($rollWidth - $referenceWidth, 3);

        return $difference < 0 && abs($difference) > self::TOLERANCE_WIDTH_BELOW_METERS;
    }

    /**
     * Aprueba la diferencia de un rollo en revisión (pending_review → approved_with_difference).
     *
     * @throws ValidationException
     */
    public function approveRollDifference(int $rollId, ?string $notes = null): FabricRoll
    {
        return DB::transaction(function () use ($rollId, $notes) {
            $roll = $this->lockRollForToleranceDecision($rollId);
            $invoice = $this->invoiceOfRollPendingReception($roll);

            if ($roll->tolerance_status !== FabricRoll::TOLERANCE_PENDING_REVIEW) {
                throw ValidationException::withMessages([
                    'general' => ['Solo se puede aprobar la diferencia de un rollo pendiente de revisión.'],
                ]);
            }

            $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

            $roll->update([
                'tolerance_status' => FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE,
                'tolerance_reason' => $notes,
            ]);

            $this->recordRollReceptionEvent(
                $invoice,
                PurchaseDocumentReceptionEvent::TYPE_ROLL_DIFFERENCE_APPROVED,
                null,
                'Rollo '.$roll->roll_number.($notes !== null ? ': '.$notes : '')
            );

            return $roll->fresh(['creditNoteLinks.creditNote']);
        });
    }

    /**
     * Rechaza un rollo de la recepción (motivo obligatorio). El rollo queda fuera del stock disponible
     * (approval_status = not_approved) y no genera movimiento de inventario.
     *
     * @throws ValidationException
     */
    public function rejectRoll(int $rollId, ?string $reason): FabricRoll
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => ['Indica el motivo del rechazo del rollo.']]);
        }

        return DB::transaction(function () use ($rollId, $reason) {
            $roll = $this->lockRollForToleranceDecision($rollId);
            $invoice = $this->invoiceOfRollPendingReception($roll);

            if ($roll->tolerance_status === FabricRoll::TOLERANCE_REJECTED) {
                throw ValidationException::withMessages([
                    'general' => ['El rollo ya fue rechazado.'],
                ]);
            }
            if ($roll->approval_status !== FabricRoll::APPROVAL_PENDING) {
                throw ValidationException::withMessages([
                    'general' => ['Solo se puede rechazar un rollo que aún no fue recibido en inventario.'],
                ]);
            }

            $roll->update([
                'tolerance_status' => FabricRoll::TOLERANCE_REJECTED,
                'approval_status' => FabricRoll::APPROVAL_NOT_APPROVED,
                'tolerance_reason' => $reason,
            ]);

            $this->recordRollReceptionEvent(
                $invoice,
                PurchaseDocumentReceptionEvent::TYPE_ROLL_REJECTED,
                $reason,
                'Rollo '.$roll->roll_number
            );

            return $roll->fresh(['creditNoteLinks.creditNote']);
        });
    }

    protected function lockRollForToleranceDecision(int $rollId): FabricRoll
    {
        return FabricRoll::with('purchaseDocumentItem.document')->lockForUpdate()->findOrFail($rollId);
    }

    /**
     * Factura del rollo, que debe seguir pendiente de recepción (ni aprobada ni rechazada).
     *
     * @throws ValidationException
     */
    protected function invoiceOfRollPendingReception(FabricRoll $roll): PurchaseDocument
    {
        $invoice = $roll->purchaseDocumentItem?->document;
        if (! $invoice) {
            throw ValidationException::withMessages([
                'general' => ['El rollo no pertenece a una factura de compra.'],
            ]);
        }

        $this->ensureInvoiceReceptionAllowed($invoice);

        if ($invoice->status !== 'pending_receipt') {
            throw ValidationException::withMessages([
                'general' => ['Solo se pueden aprobar o rechazar rollos mientras la factura está pendiente de recepción.'],
            ]);
        }

        return $invoice;
    }

    protected function recordRollReceptionEvent(PurchaseDocument $invoice, string $type, ?string $reason, ?string $notes): PurchaseDocumentReceptionEvent
    {
        return PurchaseDocumentReceptionEvent::create([
            'purchase_document_id' => $invoice->id,
            'event_type' => $type,
            'reason' => $reason,
            'notes' => $notes,
            'user_id' => Auth::id(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @throws ValidationException si queda algún rollo de la factura pendiente de aprobar o rechazar.
     */
    protected function ensureNoRollsPendingReview(PurchaseDocument $invoice): void
    {
        $pending = FabricRoll::whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
            $q->where('purchase_document_id', $invoice->id);
        })
            ->where('tolerance_status', FabricRoll::TOLERANCE_PENDING_REVIEW)
            ->count();

        if ($pending > 0) {
            throw ValidationException::withMessages([
                'general' => [sprintf(
                    'Hay %d rollos pendientes de aprobar o rechazar antes de aprobar la recepción completa.',
                    $pending
                )],
                'rolls_pending_review' => [(string) $pending],
            ]);
        }
    }

    /**
     * Ítems de tela cuya recepción se aparta del pedido en más de TOLERANCE_QUANTITY_METERS mts,
     * de más o de menos. Ambos casos exigen registrar el motivo y evidencia para su aprobación.
     *
     * @return list<array{name:string, ordered:float, received:float, difference:float}>
     */
    public function receptionDifferences(PurchaseDocument $invoice): array
    {
        $invoice->loadMissing(['items', 'items.item.categorySubGroup']);

        $differences = [];
        foreach ($invoice->items as $item) {
            if (! $item instanceof PurchaseDocumentItem || ! $this->isFabricDocumentItem($item)) {
                continue;
            }

            $ordered = (float) $item->quantity;
            if ($ordered <= 0) {
                continue;
            }
            $received = $this->sumRollQuantityForPurchaseDocumentItem($item->id);
            $difference = $ordered - $received;
            if (abs($difference) > self::TOLERANCE_QUANTITY_METERS) {
                $differences[] = [
                    'name' => (string) ($item->item_name ?? $item->item?->name ?? "Ítem #{$item->id}"),
                    'ordered' => $ordered,
                    'received' => $received,
                    'difference' => $difference,
                ];
            }
        }

        return $differences;
    }

    /**
     * Aviso (no bloqueante) al registrar un rollo cuando ya se recibió de más respecto a lo facturado.
     * Faltante no se avisa aquí: durante la recepción rollo a rollo es lo normal.
     */
    public function receptionExcessWarning(PurchaseDocumentItem $line): ?string
    {
        $ordered = (float) $line->quantity;
        if ($ordered <= 0) {
            return null;
        }

        $received = $this->sumRollQuantityForPurchaseDocumentItem($line->id);
        $excess = $received - $ordered;
        if ($excess <= self::TOLERANCE_QUANTITY_METERS) {
            return null;
        }

        return sprintf(
            'Se recibieron %.2f mts de más en "%s" (facturado %.2f mts, recibido %.2f mts). No bloquea el registro del rollo, pero al aprobar la recepción se pedirá el motivo y evidencia de la diferencia.',
            $excess,
            (string) ($line->item_name ?? $line->item?->name ?? "Ítem #{$line->id}"),
            $ordered,
            $received
        );
    }

    /**
     * Impide aprobar la recepción mientras haya una diferencia fuera de tolerancia sin aprobar.
     * Quien recepciona registra la diferencia (reportReceptionDifference) y otra persona con
     * permiso para gestionar diferencias la aprueba; recién ahí se puede aprobar y entra al inventario.
     *
     * @throws ValidationException
     */
    protected function ensureReceptionDifferencesApproved(PurchaseDocument $invoice): void
    {
        if ($invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages([
                'general' => ['La diferencia de esta recepción está pendiente de aprobación por quien tenga permiso para gestionar diferencias. El inventario no se carga hasta entonces.'],
                'difference_pending' => ['1'],
            ]);
        }

        $failing = $this->receptionDifferences($invoice);
        if (count($failing) === 0 || $invoice->difference_status === 'approved') {
            return;
        }

        $detail = implode('; ', array_map(
            fn (array $row) => sprintf('%s: facturado %.2f mts, recibido %.2f mts (diferencia %.2f mts)', $row['name'], $row['ordered'], $row['received'], $row['difference']),
            $failing
        ));

        throw ValidationException::withMessages([
            'general' => [
                'La recepción se aparta de lo facturado en más de '.self::TOLERANCE_QUANTITY_METERS.' mts (de más o de menos). Registre la diferencia con su motivo y evidencia para que la apruebe alguien con permiso; el inventario no se carga hasta entonces. '.$detail,
            ],
            'difference_required' => ['1'],
        ]);
    }

    /**
     * Registra el motivo y la evidencia de la diferencia de recepción y la deja pendiente de aprobación.
     *
     * @throws ValidationException
     */
    public function reportReceptionDifference($invoiceId, string $reason, string $evidence): PurchaseDocument
    {
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);

        if ($invoice->status === 'approved') {
            throw ValidationException::withMessages(['general' => ['La factura ya está recepcionada.']]);
        }
        if ($invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages(['general' => ['La diferencia ya fue registrada y está pendiente de aprobación.']]);
        }

        $failing = $this->receptionDifferences($invoice);
        if (count($failing) === 0) {
            throw ValidationException::withMessages(['general' => ['La recepción no tiene diferencias fuera de tolerancia.']]);
        }

        $summary = implode('; ', array_map(
            fn (array $row) => sprintf('%s: facturado %.2f mts, recibido %.2f mts', $row['name'], $row['ordered'], $row['received']),
            $failing
        ));

        return app(\App\Services\Expenses\PurchaseDocumentService::class)
            ->attachValidationDifference($invoice, $reason, $evidence, $summary);
    }

    protected function isFabricDocumentItem(PurchaseDocumentItem $item): bool
    {
        if ($item->item?->categorySubGroup) {
            return MaterialClassifier::isFabricPurchaseDocumentItem($item);
        }

        $fallbackName = mb_strtolower(trim((string) ($item->item_name ?? $item->item?->name ?? '')));

        return $fallbackName !== '' && str_contains($fallbackName, 'tela');
    }

    /**
     * Rechaza la recepción de una factura pendiente. El motivo es obligatorio y queda registrado junto con
     * quién y cuándo la rechazó. Una factura rechazada ya no admite rollos, registro ni aprobación.
     */
    public function rejectReception($invoiceId, ?string $reason = null, ?string $notes = null)
    {
        $invoice = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);

        if ($invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages([
                'general' => ['La factura tiene una diferencia pendiente de aprobación; decídela antes de rechazar la recepción.'],
                'difference_pending' => ['1'],
            ]);
        }

        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => ['Indica el motivo del rechazo.']]);
        }

        if ($invoice->status !== 'pending_receipt') {
            throw ValidationException::withMessages([
                'general' => ['Solo se puede rechazar una factura pendiente de recepción.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason, $notes) {
            $invoice->update([
                'status' => 'rejected',
                'rejection_reason' => trim((string) $reason),
                'rejection_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'rejected_by_user_id' => Auth::id(),
                'rejected_at' => now(),
            ]);

            $rejectedRollsQuery = FabricRoll::whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                $q->where('purchase_document_id', $invoice->id);
            })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING);

            $rejectedRollIds = (clone $rejectedRollsQuery)->pluck('id')->all();

            $rejectedRollsQuery->update(['approval_status' => FabricRoll::APPROVAL_NOT_APPROVED]);

            app(AuditLogService::class)->logBulk(
                FabricRoll::class,
                'updated',
                $rejectedRollIds,
                ['approval_status' => FabricRoll::APPROVAL_NOT_APPROVED, 'purchase_document_id' => $invoice->id],
                'Rechazo de recepción de la factura',
            );

            return $invoice;
        });
    }

    protected function ensureInvoiceReceptionAllowed(PurchaseDocument $invoice): void
    {
        if ($invoice->status === 'rejected') {
            throw ValidationException::withMessages([
                'general' => ['La recepción de esta factura fue rechazada; ya no se puede registrar, aprobar ni añadir rollos.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($invoice)) {
            throw ValidationException::withMessages([
                'general' => ['La factura tiene diferencia rechazada y no puede volver al flujo de recepción.'],
            ]);
        }
    }

    protected function ensureReceptionNotApproved(PurchaseDocument $invoice, string $message = 'La recepción de esta factura ya fue aprobada.'): void
    {
        if (in_array(mb_strtolower(trim((string) $invoice->status)), ['approved', 'received'], true)) {
            throw ValidationException::withMessages(['general' => [$message]]);
        }
    }

    protected function isDifferenceRejectedInvoice(PurchaseDocument $invoice): bool
    {
        $approvalStatus = mb_strtolower(trim((string) ($invoice->approval_status ?? '')));
        $status = mb_strtolower(trim((string) ($invoice->status ?? '')));

        return in_array($approvalStatus, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true) || in_array($status, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true);
    }

    /**
     * Actualiza cantidades de rollos existentes desde un array de filas importadas.
     * Columnas esperadas: roll_number, quantity, partial_output, real_availability, item_reference (opcional), batch (opcional).
     * Por cada rollo actualizado crea un registro en fabric_roll_adjustments.
     * Si el delta de cantidad ≠ 0 y el rollo tiene item_id, crea movimiento de inventario tipo ajuste.
     *
     * @param  array<int, array{roll_number:string, quantity:float, partial_output:float, real_availability:float, item_reference:string, batch:string}>  $rows
     * @return array{updated:int, skipped:int, errors:list<array{row:int|null, message:string}>}
     */
    public function importQuantities(array $rows, string $batchRef): array
    {
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $itemsByRef = [];

        foreach ($rows as $rowIndex => $row) {
            // El llamador envía la fila real del archivo; el cálculo por índice es un
            // fallback y desfasa si se filtraron filas vacías antes de llegar aquí.
            $rowNum = (int) ($row['row'] ?? $rowIndex + 2);
            $rollNumber = trim((string) ($row['roll_number'] ?? ''));

            if ($rollNumber === '') {
                $skipped++;

                continue;
            }

            $roll = FabricRoll::where('roll_number', $rollNumber)->first();

            if (! $roll) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}' no encontrado. Se omite."];
                $skipped++;

                continue;
            }

            // La importación reemplaza cantidades "de la verdad" de rollos que todavía no se
            // operan en el sistema. Un rollo con descargues ya tiene su historia aquí: se
            // corrige con ajustes o corrigiendo el descargue, nunca sobrescribiéndolo.
            if ($this->hasDischarges($roll)) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ya tiene descargues registrados; no se sobrescribe por importación. Use un ajuste o corrija el descargue."];
                $skipped++;

                continue;
            }

            $newQty = FabricRollBalance::meters((float) ($row['quantity'] ?? 0));
            $newPartial = FabricRollBalance::meters((float) ($row['partial_output'] ?? 0));
            $realAvail = FabricRollBalance::meters((float) ($row['real_availability'] ?? 0));
            $newActual = max(0.0, FabricRollBalance::meters($newQty - $realAvail));

            if ($newPartial > FabricRollBalance::meters($newQty - $newActual) + FabricRollBalance::EPSILON) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': la salida parcial ({$newPartial} m) supera la disponibilidad real ({$realAvail} m)."];
                $skipped++;

                continue;
            }

            if ($newQty <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': la cantidad debe ser mayor a 0."];
                $skipped++;

                continue;
            }

            // El archivo trae la salida parcial "de la verdad" del rollo — pero si trae
            // menos de lo que ya está comprometido (ficha técnica + códigos manuales),
            // importarlo dejaría esas reservas activas sin respaldo en partial_output
            // (el mismo problema que RecalibrarPartialOutput/resetTechnicalSheet ya
            // tuvieron). Se rechaza la fila en vez de sobrescribir en silencio.
            $minimumPartial = $this->minimumRequiredPartialOutput($roll->id);
            if ($newPartial < $minimumPartial - 0.01) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': no puede importarse con salida parcial {$newPartial} m — tiene {$minimumPartial} m comprometidos entre ficha técnica y códigos manuales. Actualice esas reservas antes de reimportar."];
                $skipped++;

                continue;
            }

            // Resolver item_id si se provee referencia
            $newItemId = null;
            $itemRef = trim((string) ($row['item_reference'] ?? ''));
            if ($itemRef !== '') {
                if (! isset($itemsByRef[$itemRef])) {
                    $itemsByRef[$itemRef] = Item::where('reference', $itemRef)->value('id');
                }
                if ($itemsByRef[$itemRef]) {
                    $newItemId = (int) $itemsByRef[$itemRef];
                } else {
                    $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': referencia ítem '{$itemRef}' no encontrada. Se actualiza cantidad sin cambiar ítem."];
                }
            }

            try {
                DB::transaction(function () use ($roll, $newQty, $newPartial, $newActual, $newItemId, $row, $batchRef, &$updated) {
                    $lockedRoll = FabricRoll::with(['item', 'purchaseDocumentItem.item'])->lockForUpdate()->findOrFail($roll->id);

                    $qtyBefore = (float) $lockedRoll->quantity;
                    $partialBefore = (float) $lockedRoll->partial_output;
                    $actualOutputBefore = (float) $lockedRoll->actual_output;
                    $oldItem = $this->rollItem($lockedRoll);
                    $oldReal = FabricRollBalance::real($lockedRoll);

                    $updateData = [
                        'quantity' => $newQty,
                        'partial_output' => $newPartial,
                        'actual_output' => $newActual,
                    ];

                    if ($newItemId !== null) {
                        $updateData['item_id'] = $newItemId;
                    }

                    $batch = trim((string) ($row['batch'] ?? ''));
                    if ($batch !== '') {
                        $updateData['batch'] = $batch;
                    }

                    $lockedRoll->update($updateData);
                    $lockedRoll->refresh()->load(['item', 'purchaseDocumentItem.item']);

                    // El kardex sigue al saldo REAL (cantidad − consumido), no solo a la
                    // cantidad: antes un cambio de actual_output o de ítem no movía el kardex.
                    $movement = $this->syncRollKardex(
                        $lockedRoll,
                        $oldItem,
                        $oldReal,
                        $this->rollItem($lockedRoll),
                        FabricRollBalance::real($lockedRoll),
                        'Ajuste por importación masiva rollo #'.$lockedRoll->roll_number.'. Lote: '.$batchRef,
                    );
                    $inventoryMovementId = $movement?->id;

                    $this->recordAdjustmentWithMovement(
                        $lockedRoll,
                        'import_update',
                        $qtyBefore,
                        $newQty,
                        'Importación masiva de cantidades',
                        reference: $batchRef,
                        inventoryMovementId: $inventoryMovementId,
                        partialBefore: $partialBefore,
                        partialAfter: $newPartial,
                        actualOutputBefore: $actualOutputBefore,
                        actualOutputAfter: $newActual,
                    );

                    $updated++;
                });
            } catch (ValidationException $e) {
                // Cada fila es atómica: si el kardex la rechaza, la fila no se aplica y se reporta.
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ".collect($e->errors())->flatten()->implode(' ')];
                $skipped++;
            } catch (\RuntimeException|\InvalidArgumentException $e) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ".$e->getMessage()];
                $skipped++;
            }
        }

        return compact('updated', 'skipped', 'errors');
    }

    /**
     * Cuánto de partial_output está realmente comprometido para un rollo según las
     * programaciones vigentes. Es el piso que importQuantities() no puede pisar al
     * reemplazar partial_output desde un archivo.
     */
    private function minimumRequiredPartialOutput(int $fabricRollId): float
    {
        return FabricRollBalance::committedByRoll([$fabricRollId])[$fabricRollId] ?? 0.0;
    }

    /**
     * Precio por metro de un rollo sin descargues. Con descargues el rollo queda bloqueado:
     * su costo ya salió al kardex con el precio vigente.
     *
     * @throws ValidationException
     */
    public function updatePrice(FabricRoll $fabricRoll, float $pricePerMeter): FabricRoll
    {
        $this->ensureRollEditable($fabricRoll);

        $fabricRoll->update(['price_per_meter' => round($pricePerMeter, 4)]);

        $itemId = $fabricRoll->item_id ?? $fabricRoll->purchaseDocumentItem?->item_id;
        if ($itemId) {
            $this->refreshWarehouseItemPP((int) $itemId);
        }

        return $fabricRoll->refresh();
    }

    /**
     * Recalcula el PP ponderado en warehouse_items para un ítem de tela, ponderando el
     * precio de cada rollo aprobado por su saldo real (lo que todavía está en bodega), no
     * por su cantidad bruta: un rollo agotado ya no pesa en el costo del inventario.
     * El precio del rollo cae al de su línea de factura si no tiene uno propio.
     *
     * Se llama tras actualizar price_per_meter de un rollo para que el cambio
     * se propague inmediatamente al costo de producción y al inventario.
     */
    public function refreshWarehouseItemPP(int $itemId): void
    {
        $price = 'COALESCE(NULLIF(fr.price_per_meter, 0), pdi.price)';
        $real = FabricRollBalance::sqlReal('fr');

        $result = DB::table('fabric_rolls as fr')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) = ?', [$itemId])
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw("{$price} > 0")
            ->whereRaw("{$real} > 0")
            ->selectRaw("SUM({$real} * {$price}) / SUM({$real}) AS new_pp")
            ->selectRaw("SUM({$real}) AS total_qty")
            ->first();

        if (! $result || (float) ($result->total_qty ?? 0) <= 0) {
            return;
        }

        $newPP = round((float) $result->new_pp, 4);

        // Actualizar warehouse_items del ítem: precio ponderado y total_cost
        WarehouseItem::where('item_id', $itemId)
            ->each(function (WarehouseItem $wi) use ($newPP) {
                $wi->weighted_average_price = $newPP;
                $wi->total_cost = round((float) $wi->quantity * $newPP, 4);
                $wi->save();
            });
    }
}
