<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentReceptionEvent;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Services\Expenses\PurchaseDocumentService;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function tolFabricInvoice(float $invoicedMeters): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS TOLERANCIA TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela tolerancia test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela tolerancia '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
    ]);
    Warehouse::firstOrCreate(['name' => 'Bodega tolerancia tela'], ['observation' => 'Test']);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-TOL-'.uniqid(),
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => $invoicedMeters,
        'price' => 100,
        'total' => $invoicedMeters * 100,
    ]);

    return [$invoice, $line, $item];
}

function tolRegisterRoll(FabricRollService $service, PurchaseDocumentItem $line, float $meters, ?string $postApprovalReason = null): FabricRoll
{
    return $service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => $meters,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
        'post_approval_reason' => $postApprovalReason,
    ]);
}

/** Registra y aprueba la diferencia a nivel de factura (flujo previo, independiente de la revisión por rollo). */
function tolApproveInvoiceDifference(FabricRollService $service, PurchaseDocument $invoice): void
{
    $service->reportReceptionDifference($invoice->id, 'Diferencia de prueba', 'evidencia-test.jpg');
    app(PurchaseDocumentService::class)->approveDifference($invoice->fresh(), null, User::factory()->create()->id);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('sends every roll of a line to pending_review when the line leaves the tolerance, and back to none when it returns', function () {
    [, $line] = tolFabricInvoice(500);

    $a = tolRegisterRoll($this->service, $line, 100);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_PENDING_REVIEW);

    // 100 + 380 = 480 (faltante 20 <= 30): vuelve a tolerancia.
    $b = tolRegisterRoll($this->service, $line, 380);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_NONE)
        ->and($b->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_NONE);

    // Excedente: 480 + 60 = 540 (exceso 40 > 30): todos a revisión.
    $c = tolRegisterRoll($this->service, $line, 60);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_PENDING_REVIEW)
        ->and($c->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_PENDING_REVIEW);

    // Borrar el rollo excedente devuelve la línea a tolerancia.
    $this->service->delete($c);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_NONE);

    // Editar una cantidad también recalcula.
    $this->service->update($b->fresh(), ['quantity' => 100, 'roll_width' => 1.5, 'entry_date' => now()->toDateString()]);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_PENDING_REVIEW);
});

it('never overwrites approved_with_difference or rejected rolls when the line is recalculated', function () {
    [, $line] = tolFabricInvoice(500);

    $a = tolRegisterRoll($this->service, $line, 100);
    $b = tolRegisterRoll($this->service, $line, 100);
    $this->service->approveRollDifference($a->id);
    $this->service->rejectRoll($b->id, 'Rollo manchado');

    // La línea vuelve a tolerancia: las decisiones tomadas se conservan.
    tolRegisterRoll($this->service, $line, 280);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE)
        ->and($b->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_REJECTED);

    // Y vuelve a salir: tampoco se pisan.
    tolRegisterRoll($this->service, $line, 200);
    expect($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE)
        ->and($b->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_REJECTED);
});

it('requires a reason to reject a roll', function () {
    [, $line] = tolFabricInvoice(500);
    $roll = tolRegisterRoll($this->service, $line, 500);

    foreach ([null, '', '   '] as $reason) {
        try {
            $this->service->rejectRoll($roll->id, $reason);
            $this->fail('Debió exigir el motivo.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('rejection_reason');
        }
    }

    expect($roll->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_NONE)
        ->and($roll->fresh()->approval_status)->toBe(FabricRoll::APPROVAL_PENDING);
});

it('rejects a roll: trimmed reason, not_approved, no inventory movement, and a reception event', function () {
    [$invoice, $line] = tolFabricInvoice(500);
    $roll = tolRegisterRoll($this->service, $line, 500);

    $rejected = $this->service->rejectRoll($roll->id, '  Tela con humedad  ');

    expect($rejected->tolerance_status)->toBe(FabricRoll::TOLERANCE_REJECTED)
        ->and($rejected->approval_status)->toBe(FabricRoll::APPROVAL_NOT_APPROVED)
        ->and($rejected->tolerance_reason)->toBe('Tela con humedad')
        ->and(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(0);

    $event = PurchaseDocumentReceptionEvent::where('purchase_document_id', $invoice->id)
        ->where('event_type', PurchaseDocumentReceptionEvent::TYPE_ROLL_REJECTED)->first();
    expect($event)->not->toBeNull()
        ->and($event->reason)->toBe('Tela con humedad')
        ->and($event->user_id)->toBe(auth()->id())
        ->and($event->occurred_at)->not->toBeNull();

    expect(fn () => $this->service->rejectRoll($roll->id, 'Otra vez'))->toThrow(ValidationException::class);
});

it('approves the difference of a pending_review roll and records the event', function () {
    [$invoice, $line] = tolFabricInvoice(500);
    $ok = tolRegisterRoll($this->service, $line, 100);

    $approved = $this->service->approveRollDifference($ok->id, ' Aceptado con el proveedor ');

    expect($approved->tolerance_status)->toBe(FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE)
        ->and($approved->approval_status)->toBe(FabricRoll::APPROVAL_PENDING)
        ->and($approved->tolerance_reason)->toBe('Aceptado con el proveedor');

    $event = PurchaseDocumentReceptionEvent::where('purchase_document_id', $invoice->id)
        ->where('event_type', PurchaseDocumentReceptionEvent::TYPE_ROLL_DIFFERENCE_APPROVED)->first();
    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBe(auth()->id());

    // Solo se aprueba lo que está pendiente de revisión.
    [, $line2] = tolFabricInvoice(500);
    $inTolerance = tolRegisterRoll($this->service, $line2, 500);
    expect(fn () => $this->service->approveRollDifference($inTolerance->id))->toThrow(ValidationException::class);
});

it('blocks approveReception while rolls are pending review and passes once they are resolved', function () {
    [$invoice, $line, $item] = tolFabricInvoice(500);
    $roll = tolRegisterRoll($this->service, $line, 400);
    $other = tolRegisterRoll($this->service, $line, 5);
    tolApproveInvoiceDifference($this->service, $invoice);

    try {
        $this->service->approveReception($invoice->id);
        $this->fail('Debió bloquear por rollos pendientes.');
    } catch (ValidationException $e) {
        expect($e->errors()['general'][0])
            ->toBe('Hay 2 rollos pendientes de aprobar o rechazar antes de aprobar la recepción completa.');
    }
    expect($invoice->fresh()->status)->toBe('pending_receipt')
        ->and(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(0);

    $this->service->approveRollDifference($roll->id);
    $this->service->approveRollDifference($other->id);
    $this->service->approveReception($invoice->id);

    expect($invoice->fresh()->status)->toBe('approved')
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(2);
});

it('does not create an inventory movement for a rejected roll nor count it as available, but counts it as received', function () {
    [$invoice, $line, $item] = tolFabricInvoice(500);
    $good = tolRegisterRoll($this->service, $line, 300);
    $bad = tolRegisterRoll($this->service, $line, 200);

    $this->service->rejectRoll($bad->id, 'Defecto de tejido');

    // Lo rechazado sigue siendo "recibido" para la tolerancia de la línea: 300 + 200 = 500.
    expect($this->service->receptionDifferences($invoice->fresh()))->toBeEmpty();

    $this->service->approveReception($invoice->id);

    $movements = InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->get();
    expect($movements)->toHaveCount(1)
        ->and($movements->first()->source_reference)->toBe($good->roll_number)
        ->and($bad->fresh()->approval_status)->toBe(FabricRoll::APPROVAL_NOT_APPROVED)
        ->and($good->fresh()->approval_status)->toBe(FabricRoll::APPROVAL_APPROVED);

    $availableIds = collect($this->service->inventoryWithFilters([], 100)->items())->pluck('id')->all();
    expect($availableIds)->toContain($good->id)
        ->and($availableIds)->not->toContain($bad->id);
});

it('does not allow approving or rejecting rolls once the invoice is no longer pending reception', function () {
    [$invoice, $line] = tolFabricInvoice(500);
    $roll = tolRegisterRoll($this->service, $line, 500);
    $this->service->approveReception($invoice->id);

    expect(fn () => $this->service->rejectRoll($roll->id, 'Tarde'))->toThrow(ValidationException::class);
    expect($roll->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_NONE);

    [$invoice2, $line2] = tolFabricInvoice(500);
    $roll2 = tolRegisterRoll($this->service, $line2, 100);
    $this->service->rejectReception($invoice2->id, 'Mercancía dañada');

    expect(fn () => $this->service->approveRollDifference($roll2->id))->toThrow(ValidationException::class);
    expect(fn () => $this->service->rejectRoll($roll2->id, 'Ya rechazada la factura'))->toThrow(ValidationException::class);
});

it('exposes the roll tolerance endpoints behind the reception permissions and validates the reason', function () {
    [, $line] = tolFabricInvoice(500);
    $roll = tolRegisterRoll($this->service, $line, 100);

    $noPermission = User::factory()->create();
    $this->actingAs($noPermission)
        ->patchJson("/kardex-telas/recepcion/rollos/{$roll->id}/rechazar", ['rejection_reason' => 'x'])
        ->assertForbidden();

    foreach (['ver telas rollos', 'aprobar recepcion tela', 'rechazar recepcion tela'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['ver telas rollos', 'aprobar recepcion tela', 'rechazar recepcion tela']);
    $this->actingAs($user);

    $this->patchJson("/kardex-telas/recepcion/rollos/{$roll->id}/rechazar", [])
        ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

    $this->patchJson("/kardex-telas/recepcion/rollos/{$roll->id}/aprobar-diferencia", ['notes' => 'Ok'])
        ->assertOk()
        ->assertJsonPath('data.tolerance_status', FabricRoll::TOLERANCE_APPROVED_WITH_DIFFERENCE)
        ->assertJsonPath('data.tolerance_reason', 'Ok');

    $this->patchJson("/kardex-telas/recepcion/rollos/{$roll->id}/rechazar", ['rejection_reason' => 'Defecto'])
        ->assertOk()
        ->assertJsonPath('data.tolerance_status', FabricRoll::TOLERANCE_REJECTED)
        ->assertJsonPath('data.approval_status', FabricRoll::APPROVAL_NOT_APPROVED);
});

it('needs the exclusive permission (not adjust rolls) to add rolls after the reception is approved', function () {
    [$invoice, $line, $item] = tolFabricInvoice(500);
    tolRegisterRoll($this->service, $line, 500);
    $this->service->approveReception($invoice->id);

    Permission::findOrCreate('ajustar telas rollos', 'web');
    $adjuster = User::factory()->create();
    $adjuster->givePermissionTo('ajustar telas rollos');
    $this->actingAs($adjuster);
    expect(fn () => tolRegisterRoll($this->service, $line, 10))->toThrow(ValidationException::class);

    Permission::findOrCreate('agregar rollos recepcion aprobada', 'web');
    $super = User::factory()->create();
    $super->givePermissionTo('agregar rollos recepcion aprobada');
    $this->actingAs($super);
    $added = tolRegisterRoll($this->service, $line, 10, 'Rollo omitido');

    expect($added->fresh()->approval_status)->toBe(FabricRoll::APPROVAL_APPROVED)
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(2);
});
