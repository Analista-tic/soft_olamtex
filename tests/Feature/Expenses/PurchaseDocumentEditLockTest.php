<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function lockUser(array $permissions): User
{
    \Spatie\Permission\Models\Role::findOrCreate('logistica', 'web');
    $user = User::factory()->create();
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

function lockDoc(string $type, string $status, ?string $approval = null): PurchaseDocument
{
    return PurchaseDocument::forceCreate([
        'document_type' => $type,
        'document_number' => 'LOCK-' . uniqid(),
        'status' => $status,
        'approval_status' => $approval,
        'date' => now()->toDateString(),
    ]);
}

function lockRequest(User $user, string $status): PurchaseRequest
{
    return PurchaseRequest::create([
        'requester_user_id' => $user->id,
        'date' => now()->toDateString(),
        'status' => $status,
        'solicited_to' => 'gerencia',
    ]);
}

function requestPayload(array $extra = []): array
{
    return array_merge([
        'date' => now()->toDateString(),
        'description' => 'Actualizada',
        'items' => [['name' => 'Hilo', 'quantity' => 2, 'price' => 3]],
    ], $extra);
}

// ---------- Solicitudes ----------

test('a draft request can be edited but status in the PUT is ignored', function () {
    $user = lockUser(['ver propios solicitudes compra', 'editar solicitudes compra']);
    $req = lockRequest($user, 'draft');

    $this->actingAs($user)
        ->putJson("/gastos/solicitudes-compra/{$req->id}", requestPayload(['status' => 'approved']))
        ->assertOk();

    $status = $req->fresh()->status;
    expect($status->value ?? $status)->toBe('draft');
});

test('a non-draft request cannot be edited', function (string $status) {
    $user = lockUser(['ver propios solicitudes compra', 'editar solicitudes compra']);
    $req = lockRequest($user, $status);

    $this->actingAs($user)
        ->putJson("/gastos/solicitudes-compra/{$req->id}", requestPayload())
        ->assertStatus(422);
})->with(['pending', 'approved', 'processed', 'rejected', 'cancelled']);

test('a non-draft request cannot be deleted', function () {
    $user = lockUser(['ver propios solicitudes compra', 'eliminar solicitudes compra']);
    $req = lockRequest($user, 'pending');

    $this->actingAs($user)->deleteJson("/gastos/solicitudes-compra/{$req->id}")->assertStatus(422);
});

test('a request can still be created with pending status and sent to approval from draft', function () {
    $user = lockUser(['ver propios solicitudes compra', 'crear solicitudes compra', 'editar solicitudes compra']);

    $res = $this->actingAs($user)
        ->postJson('/gastos/solicitudes-compra', requestPayload(['status' => 'pending']))
        ->assertCreated();
    $created = PurchaseRequest::find($res->json('data.id'));
    expect($created->status->value ?? $created->status)->toBe('pending');

    $draft = lockRequest($user, 'draft');
    $this->actingAs($user)->postJson("/gastos/solicitudes-compra/{$draft->id}/send-to-approval")->assertOk();
    expect($draft->fresh()->status->value ?? $draft->fresh()->status)->toBe('pending');
});

// ---------- Órdenes ----------

test('non-pending orders cannot be edited or deleted', function (string $status) {
    $user = lockUser(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra', 'eliminar ordenes compra']);
    $order = lockDoc('purchase_order', $status);

    $this->actingAs($user)
        ->putJson("/gastos/ordenes-compra/{$order->id}", [])
        ->assertStatus(422)
        ->assertJsonPath('message', PurchaseDocument::ORDER_LOCKED_MESSAGE);

    $this->actingAs($user)->deleteJson("/gastos/ordenes-compra/{$order->id}")->assertStatus(422);
    expect(PurchaseDocument::find($order->id))->not->toBeNull();
})->with(['approved', 'rejected', 'partially_invoiced', 'invoiced']);

test('a pending order can be deleted and its edit page is forbidden otherwise', function () {
    $user = lockUser(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra', 'eliminar ordenes compra']);

    $approved = lockDoc('purchase_order', 'approved');
    $this->actingAs($user)->get("/gastos/ordenes-compra/{$approved->id}/editar")->assertForbidden();

    $pending = lockDoc('purchase_order', 'pending');
    $this->actingAs($user)->deleteJson("/gastos/ordenes-compra/{$pending->id}")->assertOk();
});

// ---------- Facturas (normal y tela) ----------

dataset('invoice endpoints', [
    'normal' => ['/gastos/facturas-compra'],
    'tela' => ['/gastos/facturas-compra-tela'],
]);

dataset('locked invoices', [
    'received' => ['received', null],
    'approved' => ['approved', 'approved'],
    'rejected' => ['rejected', null],
    'difference_rejected' => ['rejected', 'difference_rejected'],
    'pending_difference_approval' => ['pending_receipt', 'pending_difference_approval'],
]);

test('locked invoices cannot be edited or deleted', function (string $base, string $status, ?string $approval) {
    $user = lockUser(['ver facturas compra', 'ver todos facturas compra', 'editar facturas compra', 'eliminar facturas compra']);
    $invoice = lockDoc('purchase_invoice', $status, $approval);

    $this->actingAs($user)->putJson("$base/{$invoice->id}", [])
        ->assertStatus(422)->assertJsonPath('message', PurchaseDocument::INVOICE_LOCKED_MESSAGE);
    $this->actingAs($user)->deleteJson("$base/{$invoice->id}")
        ->assertStatus(422)->assertJsonPath('message', PurchaseDocument::INVOICE_LOCKED_MESSAGE);

    expect(PurchaseDocument::find($invoice->id))->not->toBeNull();
})->with('invoice endpoints')->with('locked invoices');

test('a pending_receipt invoice can be deleted', function (string $base) {
    $user = lockUser(['ver facturas compra', 'ver todos facturas compra', 'eliminar facturas compra', 'editar facturas compra']);

    $invoice = lockDoc('purchase_invoice', 'pending_receipt');
    expect($invoice->canEditInvoice())->toBeTrue();
    $this->actingAs($user)->deleteJson("$base/{$invoice->id}")->assertOk();
})->with('invoice endpoints');

// ---------- Páginas de creación y edición ----------

test('the create page for requests, orders and invoices (normal and fabric) responds ok', function () {
    $user = lockUser([
        'ver propios solicitudes compra', 'crear solicitudes compra',
        'ver ordenes compra', 'crear ordenes compra',
        'ver facturas compra', 'crear facturas compra',
    ]);

    $this->actingAs($user)->get('/gastos/solicitudes-compra/crear')->assertOk();
    $this->actingAs($user)->get('/gastos/ordenes-compra/crear')->assertOk();
    $this->actingAs($user)->get('/gastos/facturas-compra/crear')->assertOk();
    $this->actingAs($user)->get('/gastos/facturas-compra-tela/crear')->assertOk();
});

test('a draft request edit page responds ok and a non-draft one is forbidden', function () {
    $user = lockUser(['ver propios solicitudes compra', 'editar solicitudes compra']);
    $draft = lockRequest($user, 'draft');
    $pending = lockRequest($user, 'pending');

    $this->actingAs($user)->get("/gastos/solicitudes-compra/{$draft->id}/editar")->assertOk();
    $this->actingAs($user)->get("/gastos/solicitudes-compra/{$pending->id}/editar")->assertForbidden();
});

test('a pending order edit page responds ok', function () {
    $user = lockUser(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra']);
    $pending = lockDoc('purchase_order', 'pending');

    $this->actingAs($user)->get("/gastos/ordenes-compra/{$pending->id}/editar")->assertOk();
});

test('the invoice edit page responds ok when pending receipt and forbidden with a pending difference', function (string $base) {
    $user = lockUser(['ver facturas compra', 'ver todos facturas compra', 'editar facturas compra']);
    $editable = lockDoc('purchase_invoice', 'pending_receipt');
    $locked = lockDoc('purchase_invoice', 'pending_receipt', 'pending_difference_approval');

    $this->actingAs($user)->get("$base/{$editable->id}/editar")->assertOk();
    $this->actingAs($user)->get("$base/{$locked->id}/editar")->assertForbidden();
})->with('invoice endpoints');
