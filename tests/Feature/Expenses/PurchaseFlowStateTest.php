<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function flowDoc(string $type, string $status, array $extra = []): PurchaseDocument
{
    return PurchaseDocument::forceCreate(array_merge([
        'document_type' => $type,
        'document_number' => 'TEST-' . uniqid(),
        'status' => $status,
        'date' => now()->toDateString(),
    ], $extra));
}

function flowUser(array $permissions): User
{
    foreach (['admin', 'logistica', 'contabilidad'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $user = User::factory()->create();
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

function statusOf($model): string
{
    $status = $model->fresh()->status;

    return (string) ($status->value ?? $status);
}

test('closing a partially invoiced order marks its request as processed', function () {
    $request = PurchaseRequest::factory()->approved()->create();
    $order = flowDoc('purchase_order', 'partially_invoiced', ['purchase_request_id' => $request->id]);
    $user = flowUser(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/cerrar", ['closure_reason' => 'Proveedor sin stock'])
        ->assertOk();

    expect(statusOf($order))->toBe('invoiced')
        ->and(statusOf($request))->toBe('processed');
});

test('deleting the only invoice returns the order to approved and reopens the request', function () {
    $request = PurchaseRequest::factory()->processed()->create();
    $order = flowDoc('purchase_order', 'invoiced', ['purchase_request_id' => $request->id]);
    $invoice = flowDoc('purchase_invoice', 'pending_receipt', ['parent_document_id' => $order->id]);
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra', 'eliminar facturas compra']);

    $this->actingAs($user)->deleteJson("/gastos/facturas-compra/{$invoice->id}")->assertOk();

    expect(statusOf($order))->toBe('approved')
        ->and(statusOf($request))->toBe('approved');
});

test('deleting one of several invoices leaves the order partially invoiced', function () {
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra', 'eliminar facturas compra']);
    $this->actingAs($user);
    [$order, $item] = orderWithItem(10);
    $invoiceA = invoiceFor($order, $item, 4);
    invoiceFor($order, $item, 6);

    $this->actingAs($user)->deleteJson("/gastos/facturas-compra/{$invoiceA->id}")->assertOk();

    expect(statusOf($order))->toBe('partially_invoiced');
});

test('a received fabric invoice can no longer be edited or deleted', function () {
    $invoice = flowDoc('purchase_invoice', 'approved');
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra', 'editar facturas compra', 'eliminar facturas compra']);

    $this->actingAs($user)->putJson("/gastos/facturas-compra-tela/{$invoice->id}", [])->assertStatus(422);
    $this->actingAs($user)->deleteJson("/gastos/facturas-compra-tela/{$invoice->id}")->assertStatus(422);
});

test('a received invoice cannot be rejected', function () {
    $invoice = flowDoc('purchase_invoice', 'received');
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra', 'anular factura compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/facturas-compra/{$invoice->id}/rechazar", ['rejection_reason' => 'x'])
        ->assertStatus(422);
});

function orderItemsPayload(array $itemIds): array
{
    return array_map(fn ($id) => [
        'item_id' => $id, 'price' => 100, 'tax' => 19, 'quantity' => 5, 'total' => 500, 'total_tax' => 95,
        'supplier_code' => 'COD-1', 'supplier_color' => 'Azul', 'order_width' => '1.5',
    ], $itemIds);
}

function orderPayload(array $itemIds): array
{
    return [
        'provider_name' => 'Proveedor prueba',
        'date' => now()->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'supplier_order_number' => 'PED-1',
        'items' => orderItemsPayload($itemIds),
    ];
}

test('an order cannot mix fabric items with regular items', function () {
    $group = \App\Models\MasterData\CategoryGroup::firstOrCreate(['name' => 'MIXTA TEST']);
    $tela = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Tela', 'category_group_id' => $group->id]);
    $otro = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Insumo', 'category_group_id' => $group->id]);
    $mk = fn ($sub, $n) => \App\Models\MasterData\Item::create(['category_sub_group_id' => $sub->id, 'name' => $n, 'description' => $n, 'status' => true])->id;
    $t1 = $mk($tela, 'Tela A');
    $t2 = $mk($tela, 'Tela B');
    $n1 = $mk($otro, 'Insumo A');
    $user = flowUser(['ver ordenes compra', 'crear ordenes compra']);

    $this->actingAs($user)->postJson('/gastos/ordenes-compra', orderPayload([$t1, $n1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');

    $this->actingAs($user)->postJson('/gastos/ordenes-compra', orderPayload([$t1, $t2]))->assertCreated();
});

function orderWithItem(float $quantity): array
{
    $group = \App\Models\MasterData\CategoryGroup::firstOrCreate(['name' => 'RECALC TEST']);
    $sub = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Insumo recalc', 'category_group_id' => $group->id]);
    $item = \App\Models\MasterData\Item::create(['category_sub_group_id' => $sub->id, 'name' => 'Item '.uniqid(), 'description' => 'x', 'status' => true]);
    $order = flowDoc('purchase_order', 'approved');
    \App\Models\Expenses\PurchaseDocumentItem::create([
        'purchase_document_id' => $order->id, 'item_id' => $item->id, 'quantity' => $quantity, 'price' => 10, 'total' => $quantity * 10,
    ]);

    return [$order, $item];
}

function invoiceFor($order, $item, float $quantity, ?string $reason = null): PurchaseDocument
{
    return app(\App\Services\Expenses\PurchaseDocumentInvoiceService::class)->createInvoice(
        ['parent_document_id' => $order->id, 'date' => now()->toDateString(), 'supplier_invoice_number' => 'F-'.uniqid(), 'reason' => $reason],
        [['item_id' => $item->id, 'quantity' => $quantity, 'price' => 10, 'tax' => 0, 'total' => $quantity * 10, 'total_tax' => 0]],
    );
}

test('an order with any invoice cannot change items, prices or supplier, but delivery date and notes stay editable', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);
    invoiceFor($order, $item, 4);
    $service = app(\App\Services\Expenses\PurchaseDocumentService::class);
    $line = fn (float $qty, float $price) => [['item_id' => $item->id, 'quantity' => $qty, 'price' => $price, 'tax' => 0, 'total' => $qty * $price, 'total_tax' => 0]];

    expect(fn () => $service->updateDocument($order->fresh(), [], $line(12, 10)))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $service->updateDocument($order->fresh(), [], $line(10, 15)))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $service->updateDocument($order->fresh(), ['contact_id' => 999999], null))->toThrow(\Illuminate\Validation\ValidationException::class);

    $service->updateDocument($order->fresh(), ['delivery_date' => '2030-01-15', 'description' => 'Nota nueva'], $line(10, 10));

    expect($order->fresh()->description)->toBe('Nota nueva')
        ->and($order->fresh()->items()->count())->toBe(1)
        ->and((float) $order->fresh()->items()->first()->quantity)->toEqual(10.0);
});

test('fabric items in orders and invoices require supplier code, supplier color and ordered width', function () {
    $user = flowUser(['ver ordenes compra', 'crear ordenes compra', 'ver facturas compra', 'crear facturas compra']);
    $group = \App\Models\MasterData\CategoryGroup::firstOrCreate(['name' => 'TELAS TEST']);
    $sub = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Tela', 'category_group_id' => $group->id]);
    $fabric = \App\Models\MasterData\Item::create(['category_sub_group_id' => $sub->id, 'name' => 'Tela '.uniqid(), 'description' => 'x', 'status' => true]);
    $line = ['item_id' => $fabric->id, 'quantity' => 10, 'price' => 5, 'tax' => 0, 'total' => 50, 'total_tax' => 0];
    $orderPayload = ['provider_name' => 'Proveedor', 'date' => now()->toDateString(), 'delivery_date' => now()->addDays(5)->toDateString(), 'supplier_order_number' => 'OC-1'];

    $this->actingAs($user)->postJson('/gastos/ordenes-compra', $orderPayload + ['items' => [$line]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.supplier_code', 'items.0.supplier_color', 'items.0.order_width']);

    $this->actingAs($user)->postJson('/gastos/facturas-compra-tela', [
        'provider_name' => 'Proveedor', 'date' => now()->toDateString(), 'supplier_invoice_number' => 'F-1', 'items' => [$line],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.supplier_code', 'items.0.supplier_color', 'items.0.order_width']);
});

test('the invoice traceability shows request, order, estimated delivery, reception and invoice dates', function () {
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra', 'recibir factura compra']);
    $this->actingAs($user);
    $request = PurchaseRequest::factory()->approved()->create();
    [$order, $item] = orderWithItem(10);
    \App\Models\Inventory\Warehouse::create(['name' => 'Bodega Trazabilidad', 'observation' => 'x']);
    // La contabilización no es lo que se prueba aquí (requiere plan de cuentas configurado).
    $this->mock(\App\Services\Finance\AccountingPostingService::class)->shouldIgnoreMissing();
    $order->update(['purchase_request_id' => $request->id, 'delivery_date' => '2030-03-10']);
    $invoice = invoiceFor($order, $item, 10);

    $before = $this->getJson("/gastos/facturas-compra/{$invoice->id}/trazabilidad")->assertOk()->json('data');
    expect($before['order']['delivery_date'])->toBe('2030-03-10')
        ->and($before['request']['number'])->toBe($request->request_number)
        ->and($before['received_at'])->toBeNull()
        ->and($before['invoice']['created_at'])->not->toBeNull();

    $this->patchJson("/gastos/facturas-compra/{$invoice->id}/recibir")->assertOk();

    $after = $this->getJson("/gastos/facturas-compra/{$invoice->id}/trazabilidad")->assertOk()->json('data');
    expect($after['received_at'])->not->toBeNull();
});

test('la trazabilidad muestra la cadena completa y solo enlaza lo que el usuario puede abrir', function () {
    $user = flowUser(['ver ordenes compra', 'ver todos ordenes compra', 'ver facturas compra', 'ver todos facturas compra']);
    $user->assignRole('admin');
    $this->actingAs($user);
    $request = PurchaseRequest::factory()->approved()->create();
    [$order, $item] = orderWithItem(10);
    $order->update(['purchase_request_id' => $request->id]);
    $first = invoiceFor($order, $item, 4);
    $second = invoiceFor($order, $item, 6);

    $fromInvoice = $this->getJson("/gastos/facturas-compra/{$first->id}/trazabilidad")->assertOk()->json('data.chain');
    expect($fromInvoice['current'])->toBe(['type' => 'invoice', 'id' => $first->id])
        ->and($fromInvoice['request']['number'])->toBe($request->request_number)
        // Sin permiso de solicitudes: se muestra pero sin enlace.
        ->and($fromInvoice['request']['url'])->toBeNull()
        ->and($fromInvoice['orders'][0]['url'])->toBe("/gastos/ordenes-compra?ver={$order->id}")
        ->and(collect($fromInvoice['invoices'])->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($fromInvoice['invoices'][1]['url'])->toBe("/gastos/facturas-compra?ver={$second->id}");

    $fromOrder = $this->getJson("/gastos/ordenes-compra/{$order->id}/trazabilidad")->assertOk()->json('data.chain');
    expect($fromOrder['current'])->toBe(['type' => 'order', 'id' => $order->id])
        ->and($fromOrder['invoices'])->toHaveCount(2);
});

test('withholdings follow the Colombian bases: ReteIVA on the IVA, ReteFuente and ICA on the subtotal', function () {
    $this->actingAs(flowUser([]));
    \App\Models\Finance\Retention::create(['name' => 'RETEIVA 15%', 'percent' => 15, 'type' => 'IVA']);
    \App\Models\Finance\Retention::create(['name' => 'RETEFUENTE 2.5%', 'percent' => 2.5, 'type' => 'FUENTE']);
    \App\Models\Finance\Retention::create(['name' => 'RETEICA 0.97', 'percent' => 0.97, 'type' => 'ICA']);
    [$order, $item] = orderWithItem(10);

    // Subtotal 1.000.000 e IVA 190.000
    $invoice = app(\App\Services\Expenses\PurchaseDocumentInvoiceService::class)->createInvoice(
        ['parent_document_id' => $order->id, 'date' => now()->toDateString(), 'supplier_invoice_number' => 'F-RET'],
        [['item_id' => $item->id, 'quantity' => 10, 'price' => 100000, 'tax' => 19, 'total' => 1000000, 'total_tax' => 190000]],
        [
            ['name' => 'RETEIVA 15%', 'percentage' => 15, 'base_amount' => null],
            ['name' => 'RETEFUENTE 2.5%', 'percentage' => 2.5, 'base_amount' => null],
            ['name' => 'RETEICA 0.97', 'percentage' => 0.97, 'base_amount' => null],
        ],
    );

    $rows = collect($invoice->fresh()->withholdings)->keyBy('name');
    expect($rows['RETEIVA 15%']['base'])->toEqual(190000.0)
        ->and($rows['RETEIVA 15%']['value'])->toEqual(28500.0)
        ->and($rows['RETEFUENTE 2.5%']['base'])->toEqual(1000000.0)
        ->and($rows['RETEFUENTE 2.5%']['value'])->toEqual(25000.0)
        ->and($rows['RETEICA 0.97']['value'])->toEqual(9700.0)
        ->and($rows['RETEIVA 15%']['base_amount'])->toBeNull();
});

test('a manually adjusted withholding base is respected', function () {
    $this->actingAs(flowUser([]));
    \App\Models\Finance\Retention::create(['name' => 'RETEIVA 15%', 'percent' => 15, 'type' => 'IVA']);
    [$order, $item] = orderWithItem(10);

    $invoice = app(\App\Services\Expenses\PurchaseDocumentInvoiceService::class)->createInvoice(
        ['parent_document_id' => $order->id, 'date' => now()->toDateString(), 'supplier_invoice_number' => 'F-RET2'],
        [['item_id' => $item->id, 'quantity' => 10, 'price' => 100000, 'tax' => 19, 'total' => 1000000, 'total_tax' => 190000]],
        [['name' => 'RETEIVA 15%', 'percentage' => 15, 'base_amount' => 100000]],
    );

    expect($invoice->fresh()->withholdings[0]['value'])->toEqual(15000.0);
});

test('the order stays open until valid invoices cover what was ordered, and rejected invoices do not count', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);

    invoiceFor($order, $item, 4);
    expect(statusOf($order))->toBe('partially_invoiced');

    $second = invoiceFor($order, $item, 6);
    expect(statusOf($order))->toBe('invoiced');

    $second->update(['status' => 'rejected']);
    app(\App\Services\Expenses\PurchaseDocumentService::class)->recalculateOrderStatus($order->fresh());
    expect(statusOf($order))->toBe('partially_invoiced');
});

test('invoicing more than pending needs a reason and is left pending approval', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);

    expect(fn () => invoiceFor($order, $item, 12))->toThrow(\Illuminate\Validation\ValidationException::class);

    $invoice = invoiceFor($order, $item, 12, 'El proveedor entregó de más');
    expect($invoice->fresh()->approval_status)->toBe(PurchaseDocument::APPROVAL_PENDING_DIFFERENCE)
        ->and(statusOf($order))->toBe('partially_invoiced');
});

test('an order with nothing pending cannot be invoiced again, even while a difference awaits approval', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);
    invoiceFor($order, $item, 12, 'El proveedor entregó de más');
    expect(statusOf($order))->toBe('partially_invoiced');

    expect(fn () => invoiceFor($order->fresh(), $item, 1, 'Otra más'))
        ->toThrow(\RuntimeException::class, 'La orden ya no tiene cantidades pendientes por facturar.');
    expect(app(\App\Services\Expenses\PurchaseDocumentService::class)->hasPendingToInvoice($order->fresh()))->toBeFalse();
});

test('a rejected invoice frees its quantity so the order can receive the correct invoice', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);
    $wrong = invoiceFor($order, $item, 10);
    expect(statusOf($order))->toBe('invoiced');

    $wrong->update(['status' => 'rejected']);
    $service = app(\App\Services\Expenses\PurchaseDocumentService::class);
    $service->recalculateOrderStatus($order->fresh());

    $fresh = $service->attachInvoicedQuantities($order->fresh());
    expect(statusOf($order))->toBe('approved')
        ->and($service->hasPendingToInvoice($order->fresh()))->toBeTrue()
        ->and($fresh->items->first()->already_invoiced_qty)->toEqual(0.0);

    invoiceFor($order->fresh(), $item, 10);
    expect(statusOf($order))->toBe('invoiced');
});

test('a manually closed order is not reopened by a recalculation', function () {
    $this->actingAs(flowUser([]));
    [$order, $item] = orderWithItem(10);
    invoiceFor($order, $item, 4);
    $order->fresh()->update(['status' => 'invoiced', 'closure_reason' => 'Sin stock']);

    app(\App\Services\Expenses\PurchaseDocumentService::class)->recalculateOrderStatus($order->fresh());

    expect(statusOf($order))->toBe('invoiced');
});

test('el filtro invoice_status coincide con el estado que muestra cada factura y es excluyente', function () {
    $user = flowUser(['ver facturas compra', 'ver todos facturas compra']);
    $invoice = fn (string $status, ?string $approval) => flowDoc(PurchaseDocument::TYPE_INVOICE, $status, ['approval_status' => $approval])->id;

    $expected = [
        'pending_receipt' => [$invoice('pending_receipt', 'pending'), $invoice('pending_receipt', 'approved'), $invoice('pending_receipt', null)],
        'pending_difference_approval' => [$invoice('pending_receipt', 'pending_difference_approval'), $invoice('approved', 'pending_difference_approval')],
        'received' => [$invoice('approved', 'pending'), $invoice('received', 'approved')],
        // "Rechazada" agrupa la recepción rechazada y la diferencia rechazada.
        'rejected' => [
            $invoice('rejected', 'pending'),
            $invoice('rejected', 'pending_difference_approval'),
            $invoice('pending_receipt', 'difference_rejected'),
            $invoice('approved', 'difference_rejected'),
        ],
    ];

    foreach ($expected as $key => $ids) {
        $returned = $this->actingAs($user)
            ->getJson('/gastos/facturas-compra/data?per_page=50&invoice_status='.$key)
            ->assertOk()
            ->json('data.*.id');

        expect(collect($returned)->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all(), "filtro {$key}");
    }
});

test('el consecutivo interno de factura no se repite aunque existan facturas de tela', function () {
    $this->actingAs(flowUser([]));
    $service = app(\App\Services\Expenses\PurchaseDocumentInvoiceService::class);
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-0001']);
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-0002']);
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-T-0001']);
    $trashed = flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-0003']);
    $trashed->delete();

    $numbers = [];
    foreach ([1, 2] as $_) {
        $numbers[] = $service->createInvoice(
            ['date' => now()->toDateString(), 'supplier_invoice_number' => 'F-'.uniqid()],
            [],
        )->document_number;
    }

    // FAC-0003 estaba borrada: no se reutiliza. FAC-T-0001 no cuenta para la serie FAC.
    expect($numbers)->toBe(['FAC-0004', 'FAC-0005'])
        ->and($service->generateFabricInvoiceNumber())->toBe('FAC-T-0002');
});

test('un proveedor no puede repetir el número de factura, ni en la de tela; otro proveedor sí', function () {
    $user = flowUser(['ver facturas compra', 'crear facturas compra', 'editar facturas compra', 'ver todos facturas compra']);
    $contact = fn (string $name) => \App\Models\MasterData\Contact::create([
        'contact_type' => 'proveedor', 'id_type' => 'nit', 'id_number' => (string) random_int(100000000, 999999999), 'full_name' => $name,
        'person_type' => 'persona_juridica', 'email' => uniqid().'@test.local', 'groups' => [], 'additional_contacts' => [], 'branches' => [],
    ])->id;
    [$a, $b] = [$contact('Proveedor A'), $contact('Proveedor B')];
    $group = \App\Models\MasterData\CategoryGroup::firstOrCreate(['name' => 'DUP TEST']);
    $sub = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Insumo dup', 'category_group_id' => $group->id]);
    $item = \App\Models\MasterData\Item::create(['category_sub_group_id' => $sub->id, 'name' => 'Item '.uniqid(), 'description' => 'x', 'status' => true]);
    $payload = fn (int $contactId, string $number) => [
        'contact_id' => $contactId, 'date' => now()->toDateString(), 'supplier_invoice_number' => $number,
        'items' => [['item_id' => $item->id, 'price' => 10, 'tax' => 0, 'quantity' => 1, 'total' => 10, 'total_tax' => 0]],
    ];

    $first = $this->actingAs($user)->postJson('/gastos/facturas-compra', $payload($a, 'ABC-1'))->assertCreated()->json('data.id');

    $this->actingAs($user)->postJson('/gastos/facturas-compra', $payload($a, ' abc-1 '))
        ->assertStatus(422)->assertJsonValidationErrors('supplier_invoice_number');
    $this->actingAs($user)->postJson('/gastos/facturas-compra', $payload($b, 'ABC-1'))->assertCreated();

    // Editar la propia factura con su mismo número no cuenta como repetido.
    $this->actingAs($user)->putJson("/gastos/facturas-compra/{$first}", $payload($a, 'ABC-1'))->assertOk();
});

test('el contador de consecutivos nunca repite y se corrige solo si ya existen números mayores', function () {
    $this->actingAs(flowUser([]));
    $service = app(\App\Services\Expenses\PurchaseDocumentInvoiceService::class);

    $numbers = collect(range(1, 5))->map(fn () => $service->generateFabricInvoiceNumber());
    expect($numbers->unique())->toHaveCount(5)->and($numbers->first())->toBe('FAC-T-0001');

    // Alguien inserta un documento con un número mayor (importación, seeder): el contador lo respeta.
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-T-0050']);
    expect($service->generateFabricInvoiceNumber())->toBe('FAC-T-0051');
});

test('la base de datos rechaza un consecutivo interno repetido y responde 422 por la API', function () {
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-0001']);

    expect(fn () => flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => 'FAC-0001']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    // Otro tipo de documento sí puede tener el mismo texto; los NULL no chocan entre sí.
    flowDoc(PurchaseDocument::TYPE_ORDER, 'approved', ['document_number' => 'FAC-0001']);
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => null]);
    flowDoc(PurchaseDocument::TYPE_INVOICE, 'approved', ['document_number' => null]);
});

test('el número de factura del proveedor lo protege la BD (carrera) y se libera al eliminar la factura', function () {
    $contactId = \App\Models\MasterData\Contact::create([
        'contact_type' => 'proveedor', 'id_type' => 'nit', 'id_number' => (string) random_int(100000000, 999999999), 'full_name' => 'Prov',
        'person_type' => 'persona_juridica', 'email' => uniqid().'@test.local', 'groups' => [], 'additional_contacts' => [], 'branches' => [],
    ])->id;
    $make = fn (string $n) => flowDoc(PurchaseDocument::TYPE_INVOICE, 'pending_receipt', ['contact_id' => $contactId, 'supplier_invoice_number' => $n, 'document_number' => 'FAC-'.uniqid()]);

    $first = $make('A-1');
    expect(fn () => $make(' a-1 '))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    $first->delete(); // soft delete: el número vuelve a estar disponible
    expect($make('A-1'))->toBeInstanceOf(PurchaseDocument::class);
});

test('un doble envío idéntico de una alta se rechaza con 409 y un reintento tras un error sí pasa', function () {
    config(['duplicate_submission.in_tests' => true]);
    \Illuminate\Support\Facades\Cache::flush();
    $user = flowUser(['ver ordenes compra', 'crear ordenes compra']);
    $group = \App\Models\MasterData\CategoryGroup::firstOrCreate(['name' => 'DOBLE ENVIO']);
    $sub = \App\Models\MasterData\CategorySubGroup::firstOrCreate(['name' => 'Insumo doble', 'category_group_id' => $group->id]);
    $item = \App\Models\MasterData\Item::create(['category_sub_group_id' => $sub->id, 'name' => 'Item '.uniqid(), 'description' => 'x', 'status' => true]);
    $payload = orderPayload([$item->id]);

    // Un envío inválido no bloquea el reintento corregido.
    $this->actingAs($user)->postJson('/gastos/ordenes-compra', array_merge($payload, ['items' => []]))->assertStatus(422);
    $this->actingAs($user)->postJson('/gastos/ordenes-compra', $payload)->assertCreated();

    // El doble clic (misma petición) no crea otra orden.
    $this->actingAs($user)->postJson('/gastos/ordenes-compra', $payload)->assertStatus(409);
    expect(PurchaseDocument::where('document_type', PurchaseDocument::TYPE_ORDER)->count())->toBe(1);
});
