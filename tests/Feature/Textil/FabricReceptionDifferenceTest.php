<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
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

function registerRoll(FabricRollService $service, PurchaseDocumentItem $line, float $meters): void
{
    $service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => $meters,
        'roll_width' => 150,
        'entry_date' => now()->toDateString(),
    ]);
}

beforeEach(function () {
    foreach (['admin', 'contabilidad', 'logistica'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('only flags a shortage beyond 30 meters per item; receiving more is not restricted', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);

    registerRoll($this->service, $line, 480);
    expect($this->service->receptionDifferences($invoice->fresh()))->toBeEmpty();

    // Excedente (540 de 500): no se restringe.
    registerRoll($this->service, $line, 60);
    expect($this->service->receptionDifferences($invoice->fresh()))->toBeEmpty();

    [$invoice2, $line2] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line2, 460);
    $differences = $this->service->receptionDifferences($invoice2->fresh());
    expect($differences)->toHaveCount(1)
        ->and($differences[0]['difference'])->toEqual(40.0);
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

    // 2) Quien recepciona registra el motivo: queda pendiente de aprobación.
    $this->service->reportReceptionDifference($invoice->id, 'El proveedor envió menos rollos');
    expect($invoice->fresh()->approval_status)->toBe(PurchaseDocument::APPROVAL_PENDING_DIFFERENCE);

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

    $this->service->approveReception($invoice->id);

    expect($invoice->fresh()->status)->toBe('approved')
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(1);
});

it('does not accept a difference report when the reception is within tolerance', function () {
    [$invoice, $line] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 500);

    expect(fn () => $this->service->reportReceptionDifference($invoice->id, 'x'))->toThrow(ValidationException::class);
});

it('lets only users who can adjust rolls add rolls after the reception is approved, straight into inventory', function () {
    [$invoice, $line, $item] = fabricReceptionInvoice(500);
    registerRoll($this->service, $line, 500);
    $this->service->approveReception($invoice->id);

    expect(fn () => registerRoll($this->service, $line, 10))->toThrow(ValidationException::class);

    Permission::findOrCreate('ajustar telas rollos', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('ajustar telas rollos');
    $this->actingAs($user);

    registerRoll($this->service, $line, 10);

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
