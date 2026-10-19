<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
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
use Spatie\Permission\Models\Role;

function fabricReceptionInvoice(float $invoicedMeters): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS RECEPCION TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela recepcion test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela recepcion '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
    ]);
    Warehouse::firstOrCreate(['name' => 'Bodega recepcion tela'], ['observation' => 'Test']);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-TEST-'.uniqid(),
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

function registerRoll(FabricRollService $service, PurchaseDocumentItem $line, float $meters, ?string $postApprovalReason = null): void
{
    $service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => $meters,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
        'post_approval_reason' => $postApprovalReason,
    ]);
}

beforeEach(function () {
    foreach (['admin', 'contabilidad', 'logistica'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('flags a shortage or an excess beyond 30 meters per item, symmetrically', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);

    registerRoll($this->service, $line, 480);
    expect($this->service->receptionDifferences($invoice->fresh()))->toBeEmpty();

    // Excedente de 40 (540 de 500): fuera de tolerancia también hacia arriba.
    registerRoll($this->service, $line, 60);
    $excessDifferences = $this->service->receptionDifferences($invoice->fresh());
    expect($excessDifferences)->toHaveCount(1)
        ->and($excessDifferences[0]['difference'])->toEqual(-40.0);

    [$invoice2, $line2] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line2, 460);
    $shortageDifferences = $this->service->receptionDifferences($invoice2->fresh());
    expect($shortageDifferences)->toHaveCount(1)
        ->and($shortageDifferences[0]['difference'])->toEqual(40.0);
});

it('warns on registration only when the received meters exceed the invoiced ones by more than 30', function () {
    [, $line] = fabricReceptionInvoice(100);

    registerRoll($this->service, $line, 50);
    expect($this->service->receptionExcessWarning($line->fresh()))->toBeNull();

    registerRoll($this->service, $line, 100);
    expect($this->service->receptionExcessWarning($line->fresh()))->toContain('Se recibieron 50.00 mts de más');
});

it('approves directly and loads inventory when the difference is within tolerance', function () {
    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 490);

    $this->service->approveReception($invoice->id);

    expect($invoice->fresh()->status)->toBe('approved')
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(1);
});

it('does not approve an already approved reception twice nor duplicate its inventory', function () {
    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 490);
    $this->service->approveReception($invoice->id);

    expect(fn () => $this->service->approveReception($invoice->id))->toThrow(ValidationException::class);
    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(1);
});

it('adds a roll after approval only with a reason and without leaving the tolerance', function () {
    Permission::findOrCreate(FabricRollService::PERMISSION_ADD_ROLLS_AFTER_APPROVAL, 'web');
    auth()->user()->givePermissionTo(FabricRollService::PERMISSION_ADD_ROLLS_AFTER_APPROVAL);

    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 490);
    $this->service->approveReception($invoice->id);

    $addRoll = fn (float $meters, ?string $reason) => $this->service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => $meters,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
        'post_approval_reason' => $reason,
    ]);

    // Sin motivo: no se permite.
    try {
        $addRoll(10, '  ');
        $this->fail('Debió exigir el motivo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('post_approval_reason');
    }

    // 490 + 60 = 550 frente a 500: sale de la tolerancia sin aprobación.
    try {
        $addRoll(60, 'Rollo olvidado');
        $this->fail('Debió bloquear por tolerancia.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }

    // 490 + 20 = 510: dentro de tolerancia, entra directo al inventario y queda el motivo.
    $roll = $addRoll(20, 'Rollo olvidado al registrar');
    expect($roll->approval_status)->toBe(FabricRoll::APPROVAL_APPROVED)
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(2)
        ->and(\App\Models\Expenses\PurchaseDocumentReceptionEvent::where('purchase_document_id', $invoice->id)
            ->where('event_type', 'roll_added_after_approval')->value('reason'))->toBe('Rollo olvidado al registrar');
});

it('allows completing an approved shortage after approval even if still outside tolerance', function () {
    Permission::findOrCreate(FabricRollService::PERMISSION_ADD_ROLLS_AFTER_APPROVAL, 'web');
    auth()->user()->givePermissionTo(FabricRollService::PERMISSION_ADD_ROLLS_AFTER_APPROVAL);

    [$invoice, $line] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 400);
    $invoice->forceFill(['status' => 'approved'])->save();

    // 400 → 450: sigue a 50 mts, pero se acerca a lo facturado (completa parte del faltante).
    $roll = $this->service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => 50,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
        'post_approval_reason' => 'Llegó parte del faltante',
    ]);
    expect($roll->approval_status)->toBe(FabricRoll::APPROVAL_APPROVED);
});

it('does not reject a reception while its difference awaits approval', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 400);
    $this->service->reportReceptionDifference($invoice->id, 'Faltan rollos', 'evidencia-test.jpg');

    try {
        $this->service->rejectReception($invoice->id, 'No llegó completo');
        $this->fail('Debió exigir decidir la diferencia antes de rechazar.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('difference_pending');
    }
    expect($invoice->fresh()->status)->toBe('pending_receipt');
});

it('blocks approval outside tolerance until the difference is reported and approved by someone else', function () {
    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 400);

    // 1) Fuera de tolerancia: no aprueba y no carga inventario.
    try {
        $this->service->approveReception($invoice->id);
        $this->fail('Debió exigir la aprobación de la diferencia.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('difference_required');
    }
    expect(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(0);

    // 2) Quien recepciona registra el motivo y la evidencia: queda pendiente de aprobación.
    $this->service->reportReceptionDifference($invoice->id, 'El proveedor envió menos rollos', 'evidencia-test.jpg');
    expect($invoice->fresh()->approval_status)->toBe(PurchaseDocument::APPROVAL_PENDING_DIFFERENCE)
        ->and($invoice->fresh()->rejection_evidence_image)->toBe('evidencia-test.jpg');

    try {
        $this->service->approveReception($invoice->id);
        $this->fail('Debió seguir bloqueada mientras la diferencia está pendiente.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('difference_pending');
    }
    expect(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(0);

    // 3) Otra persona con permiso aprueba la diferencia y recién ahí se puede recepcionar.
    $approver = User::factory()->create();
    app(PurchaseDocumentService::class)->approveDifference($invoice->fresh(), null, $approver->id);

    // 4) Además, cada rollo fuera de tolerancia se resuelve individualmente antes de aprobar la factura.
    try {
        $this->service->approveReception($invoice->id);
        $this->fail('Debió exigir resolver los rollos pendientes de revisión.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('rolls_pending_review');
    }
    $roll = FabricRoll::where('purchase_document_item_id', $line->id)->firstOrFail();
    $this->service->approveRollDifference($roll->id, 'Aceptado por el proveedor');

    $this->service->approveReception($invoice->id);

    expect($invoice->fresh()->status)->toBe('approved')
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(1);
});

it('does not accept a difference report when the reception is within tolerance', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 500);

    expect(fn () => $this->service->reportReceptionDifference($invoice->id, 'x', 'evidencia-test.jpg'))->toThrow(ValidationException::class);
});

it('requires evidence to report a reception difference', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 400);

    // El grupo de rutas kardex-telas exige además 'ver telas rollos'.
    Permission::findOrCreate('ver telas rollos', 'web');
    Permission::findOrCreate('aprobar recepcion tela', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(['ver telas rollos', 'aprobar recepcion tela']);
    $this->actingAs($user);

    $response = $this->patchJson("/kardex-telas/recepcion/{$invoice->id}/reportar-diferencia", [
        'reason' => 'El proveedor envió menos rollos',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('evidence');
    expect($invoice->fresh()->approval_status)->not->toBe(PurchaseDocument::APPROVAL_PENDING_DIFFERENCE);
});

it('lets only users with the exclusive permission add rolls after the reception is approved, straight into inventory', function () {
    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 500);
    $this->service->approveReception($invoice->id);

    expect(fn () => registerRoll($this->service, $line, 10))->toThrow(ValidationException::class);

    // 'ajustar telas rollos' ya no basta para añadir rollos tras aprobar.
    Permission::findOrCreate('ajustar telas rollos', 'web');
    $adjuster = User::factory()->create();
    $adjuster->givePermissionTo('ajustar telas rollos');
    $this->actingAs($adjuster);
    expect(fn () => registerRoll($this->service, $line, 10))->toThrow(ValidationException::class);

    Permission::findOrCreate('agregar rollos recepcion aprobada', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('agregar rollos recepcion aprobada');
    $this->actingAs($user);

    registerRoll($this->service, $line, 10, 'Rollo omitido');

    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(2);
});

it('requires a reason to reject a reception and records who and when', function () {
    [$invoice] = fabricReceptionInvoice(100);

    expect(fn () => $this->service->rejectReception($invoice->id, '  '))
        ->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('pending_receipt');

    $this->service->rejectReception($invoice->id, 'Mercancía dañada', 'Rollos mojados');

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe('rejected')
        ->and($fresh->rejection_reason)->toBe('Mercancía dañada')
        ->and($fresh->rejection_notes)->toBe('Rollos mojados')
        ->and($fresh->rejected_by_user_id)->toBe(auth()->id())
        ->and($fresh->rejected_at)->not->toBeNull();
});

it('does not allow registering rolls, reporting, approving or rejecting again once the reception is rejected', function () {
    [$invoice, $line] = fabricReceptionInvoice(100);
    $this->service->rejectReception($invoice->id, 'Mercancía dañada');

    expect(fn () => registerRoll($this->service, $line, 50))->toThrow(ValidationException::class);
    expect(fn () => $this->service->approveReception($invoice->id))->toThrow(ValidationException::class);
    expect(fn () => $this->service->rejectReception($invoice->id, 'Otro motivo'))->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('rejected');
});

it('only rejects invoices that are pending reception', function () {
    [$invoice, $line] = fabricReceptionInvoice(100);
    registerRoll($this->service, $line, 100);
    $this->service->approveReception($invoice->id);

    expect(fn () => $this->service->rejectReception($invoice->id, 'Ya recepcionada'))->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('approved');
});
