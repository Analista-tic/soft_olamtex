<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollCreditNoteLink;
use Spatie\Permission\Models\Permission;

/**
 * Rollos restringidos = rechazados en recepción (tolerance_status = rejected) y sin resolver
 * (resolved_at NULL). Nunca cuentan como disponible; se listan aparte en el Kardex de telas
 * (?restricted=1) y como `restricted_quantity` en el Inventario general.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $this->actingAs($user);
});

function restrictedFabricItem(string $suffix): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA RESTRINGIDOS TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Restringidos Test',
        'category_group_id' => $group->id,
    ]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'TELA RESTRINGIDA '.$suffix,
        'description' => 'Item de prueba '.$suffix,
        'status' => true,
    ]);
}

function restrictedRoll(Item $item, string $number, float $qty, string $approval, string $tolerance, ?string $reason = null, $resolvedAt = null, ?int $lineId = null): FabricRoll
{
    return FabricRoll::create([
        'roll_number' => $number,
        'item_id' => $item->id,
        'purchase_document_item_id' => $lineId,
        'quantity' => $qty,
        'entry_date' => now()->toDateString(),
        'approval_status' => $approval,
        'tolerance_status' => $tolerance,
        'tolerance_reason' => $reason,
        'resolved_at' => $resolvedAt,
    ]);
}

function stockRow(Item $item): WarehouseItem
{
    $warehouse = Warehouse::firstOrCreate(['name' => 'Bodega Restringidos Test'], ['observation' => 'Test']);

    return WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 0,
    ]);
}

it('lists rejected unresolved rolls only in restricted mode and keeps them out of the normal listing', function () {
    $item = restrictedFabricItem('modo');
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-RESTR-1',
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => 300,
        'price' => 100,
        'total' => 30000,
    ]);

    restrictedRoll($item, 'RL-DISP-1', 100, FabricRoll::APPROVAL_APPROVED, FabricRoll::TOLERANCE_NONE);
    restrictedRoll($item, 'RL-RESTR-1', 80, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Tela manchada', null, $line->id);
    // Rechazado pero ya resuelto (nota aprobada, dado de baja): no aparece en ningún modo.
    restrictedRoll($item, 'RL-BAJA-1', 60, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto', now(), $line->id);

    $normal = $this->getJson(route('kardex-telas.inventario-rollos.data'))->assertOk();
    $normalRolls = collect($normal->json('data'))->pluck('roll');
    expect($normalRolls)->toContain('RL-DISP-1')
        ->not->toContain('RL-RESTR-1')
        ->not->toContain('RL-BAJA-1');

    $restricted = $this->getJson(route('kardex-telas.inventario-rollos.data', ['restricted' => 1]))->assertOk();
    $restrictedRolls = collect($restricted->json('data'));
    expect($restrictedRolls->pluck('roll')->all())->toBe(['RL-RESTR-1']);

    $row = $restrictedRolls->first();
    expect($row['is_restricted'])->toBeTrue()
        ->and($row['restriction_reason'])->toBe('Tela manchada')
        ->and($row['credit_note_number'])->toBeNull()
        ->and($row['invoice_number'])->toBe('FT-RESTR-1');

    // Los disponibles no se marcan como restringidos.
    $available = collect($normal->json('data'))->firstWhere('roll', 'RL-DISP-1');
    expect($available['is_restricted'])->toBeFalse()->and($available['restriction_reason'])->toBeNull();
});

it('exposes the linked credit note number for restricted rolls', function () {
    $item = restrictedFabricItem('nota');
    $note = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'document_number' => 'NC-RESTR-77',
        'status' => 'pending',
        'date' => now()->toDateString(),
    ]);
    $roll = restrictedRoll($item, 'RL-RESTR-NC', 50, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Ancho incorrecto');
    FabricRollCreditNoteLink::create([
        'fabric_roll_id' => $roll->id,
        'credit_note_document_id' => $note->id,
    ]);

    $row = collect(
        $this->getJson(route('kardex-telas.inventario-rollos.data', ['restricted' => 1]))->assertOk()->json('data')
    )->firstWhere('roll', 'RL-RESTR-NC');

    expect($row['credit_note_number'])->toBe('NC-RESTR-77')
        ->and($row['restriction_reason'])->toBe('Ancho incorrecto');
});

it('exposes restricted_quantity in the general inventory without adding it to quantity', function () {
    $item = restrictedFabricItem('general');
    stockRow($item);

    restrictedRoll($item, 'RL-G-DISP', 100, FabricRoll::APPROVAL_APPROVED, FabricRoll::TOLERANCE_NONE);
    restrictedRoll($item, 'RL-G-RESTR-1', 40, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto');
    restrictedRoll($item, 'RL-G-RESTR-2', 25.5, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto');
    // Resuelto (dado de baja): no suma ni al disponible ni al restringido.
    restrictedRoll($item, 'RL-G-BAJA', 70, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto', now());
    // Pendiente de aprobación de recepción: tampoco suma.
    restrictedRoll($item, 'RL-G-PEND', 33, FabricRoll::APPROVAL_PENDING, FabricRoll::TOLERANCE_NONE);

    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'TELA RESTRINGIDA general']))->assertOk()->json('data'));
    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    expect((float) $row['quantity'])->toBe(100.0)
        ->and((float) $row['restricted_quantity'])->toBe(65.5)
        ->and((bool) $row['is_fabric'])->toBeTrue();
});

it('keeps a fabric item visible when it only has restricted rolls (available quantity 0)', function () {
    $item = restrictedFabricItem('solo-restringido');
    stockRow($item);
    restrictedRoll($item, 'RL-SOLO-RESTR', 90, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto');

    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'TELA RESTRINGIDA solo-restringido']))->assertOk()->json('data'));
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()['quantity'])->toBe(0.0)
        ->and((float) $rows->first()['restricted_quantity'])->toBe(90.0);
});

it('hides a fabric item without available nor restricted rolls and reports 0 restricted for non-fabric items', function () {
    $empty = restrictedFabricItem('vacio');
    stockRow($empty);
    // Solo un rollo dado de baja: no debe mantener visible el ítem.
    restrictedRoll($empty, 'RL-VACIO', 70, FabricRoll::APPROVAL_NOT_APPROVED, FabricRoll::TOLERANCE_REJECTED, 'Defecto', now());

    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'TELA RESTRINGIDA vacio']))->assertOk()->json('data'));
    expect($rows)->toBeEmpty();

    $group = CategoryGroup::firstOrCreate(['name' => 'INSUMOS RESTRINGIDOS TEST']);
    $sub = CategorySubGroup::firstOrCreate(['name' => 'Botones Restringidos Test', 'category_group_id' => $group->id]);
    $plain = Item::create([
        'category_sub_group_id' => $sub->id,
        'name' => 'BOTON NO TELA RESTRINGIDOS',
        'description' => 'No es tela',
        'status' => true,
    ]);
    $stock = stockRow($plain);
    $stock->update(['quantity' => 12]);

    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'BOTON NO TELA RESTRINGIDOS']))->assertOk()->json('data'));
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()['quantity'])->toBe(12.0)
        ->and((float) $rows->first()['restricted_quantity'])->toBe(0.0);
});
