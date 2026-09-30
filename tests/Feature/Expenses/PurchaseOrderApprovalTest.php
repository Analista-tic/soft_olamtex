<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makePurchaseDocument(string $type, string $status): PurchaseDocument
{
    return PurchaseDocument::forceCreate([
        'document_type' => $type,
        'document_number' => 'TEST-' . uniqid(),
        'status' => $status,
        'date' => now()->toDateString(),
    ]);
}

function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

test('a user with the approve permission can approve a pending order they did not create', function () {
    $order = makePurchaseDocument('purchase_order', 'pending');
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'aprobar orden compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/approve")
        ->assertOk()
        ->assertJsonPath('message', 'Orden de compra aprobada correctamente.');

    $status = $order->fresh()->status;
    expect($status->value ?? $status)->toBe('approved');
});

test('approving requires the approve permission', function () {
    $order = makePurchaseDocument('purchase_order', 'pending');
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/approve")
        ->assertForbidden();
});

test('an already approved order cannot be approved again', function () {
    $order = makePurchaseDocument('purchase_order', 'approved');
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'aprobar orden compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/approve")
        ->assertStatus(422);
});

test('a pending order can be rejected with a reason and is never deleted', function () {
    $order = makePurchaseDocument('purchase_order', 'pending');
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'aprobar orden compra']);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/reject", [])
        ->assertStatus(422);

    $this->actingAs($user)
        ->patchJson("/gastos/ordenes-compra/{$order->id}/reject", ['rejection_reason' => 'Precio fuera de lo acordado'])
        ->assertOk();

    $fresh = $order->fresh();
    expect($fresh->status->value ?? $fresh->status)->toBe('rejected')
        ->and($fresh->rejection_reason)->toBe('Precio fuera de lo acordado')
        ->and($fresh->rejected_by_user_id)->toBe($user->id)
        ->and($fresh->rejected_at)->not->toBeNull();
});

test('only pending orders can be rejected and it requires the approve permission', function () {
    $approved = makePurchaseDocument('purchase_order', 'approved');
    $pending = makePurchaseDocument('purchase_order', 'pending');
    $approver = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'aprobar orden compra']);
    $viewer = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra']);

    $this->actingAs($approver)
        ->patchJson("/gastos/ordenes-compra/{$approved->id}/reject", ['rejection_reason' => 'x'])
        ->assertStatus(422);
    $this->actingAs($viewer)
        ->patchJson("/gastos/ordenes-compra/{$pending->id}/reject", ['rejection_reason' => 'x'])
        ->assertForbidden();
});

test('sending the order to the supplier marks it as sent without changing its status', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $supplier = \App\Models\MasterData\Contact::factory()->create(['email' => 'proveedor@example.com']);
    $order = makePurchaseDocument('purchase_order', 'approved');
    $order->forceFill(['contact_id' => $supplier->id])->save();
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra']);

    $this->actingAs($user)->postJson("/gastos/ordenes-compra/{$order->id}/enviar-correo-pdf")->assertOk();

    $fresh = $order->fresh();
    expect($fresh->sent_at)->not->toBeNull()
        ->and($fresh->status->value ?? $fresh->status)->toBe('approved');
});

test('a pending or rejected order is not sent to the supplier', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $supplier = \App\Models\MasterData\Contact::factory()->create(['email' => 'proveedor@example.com']);
    $user = userWithPermissions(['ver ordenes compra', 'ver todos ordenes compra', 'editar ordenes compra']);

    foreach (['pending', 'rejected'] as $status) {
        $order = makePurchaseDocument('purchase_order', $status);
        $order->forceFill(['contact_id' => $supplier->id])->save();

        $this->actingAs($user)->postJson("/gastos/ordenes-compra/{$order->id}/enviar-correo-pdf")->assertStatus(422);
        expect($order->fresh()->sent_at)->toBeNull();
    }
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

test('legacy payment routes require voucher permissions', function () {
    $user = User::factory()->create();
    $invoice = makePurchaseDocument('purchase_invoice', 'pending_receipt');

    $this->actingAs($user)->postJson('/gastos/pagos', [])->assertForbidden();
    $this->actingAs($user)->getJson("/gastos/pagos/facturas/{$invoice->id}")->assertForbidden();
});
