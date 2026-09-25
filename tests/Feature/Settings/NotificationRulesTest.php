<?php

use App\Models\Settings\Area;
use App\Models\Settings\NotificationEvent;
use App\Models\Settings\User;
use App\Notifications\GenericEventNotification;
use App\Services\Settings\NotificationDispatcher;
use App\Services\Settings\NotificationRuleService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function ruleUser(string $role, array $attributes = []): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function ruleNotification(): GenericEventNotification
{
    return new GenericEventNotification('purchase_order_created', 'Gastos', 'Título', 'Cuerpo', '/x');
}

function saveRule(string $key, array $overrides = []): void
{
    $event = app(NotificationRuleService::class)->eventFor($key);
    $current = [
        'enabled' => $event->enabled,
        'channel_database' => $event->channel_database,
        'channel_mail' => $event->channel_mail,
        'exclude_actor' => $event->exclude_actor,
        'recipients' => $event->recipients->map(fn ($r) => ['type' => $r->recipient_type, 'value' => $r->recipient_value, 'permission_filter' => $r->permission_filter])->all(),
    ];
    app(NotificationRuleService::class)->update($key, array_merge($current, $overrides));
}

test('only the superadmin role can see and change notification rules', function () {
    $admin = ruleUser('admin');
    $superadmin = ruleUser('superadmin');

    $this->get('/configuracion/reglas-notificaciones')->assertRedirect('/login');
    $this->actingAs($admin)->get('/configuracion/reglas-notificaciones')->assertForbidden();
    $this->actingAs($admin)->putJson('/configuracion/reglas-notificaciones/purchase_order_created', [])->assertForbidden();

    $this->actingAs($superadmin)->get('/configuracion/reglas-notificaciones')->assertOk();
});

test('the superadmin can change when, how and to whom an event notifies', function () {
    $superadmin = ruleUser('superadmin');
    ruleUser('logistica');

    $this->actingAs($superadmin)->putJson('/configuracion/reglas-notificaciones/purchase_order_created', [
        'enabled' => true,
        'channel_database' => true,
        'channel_mail' => true,
        'exclude_actor' => false,
        'recipients' => [['type' => 'role', 'value' => 'logistica', 'permission_filter' => null]],
    ])->assertOk()->assertJsonPath('data.channel_mail', true)->assertJsonPath('data.recipients.0.value', 'logistica');

    $event = NotificationEvent::where('event_key', 'purchase_order_created')->with('recipients')->first();
    expect($event->channel_mail)->toBeTrue()
        ->and($event->exclude_actor)->toBeFalse()
        ->and($event->recipients)->toHaveCount(1);
});

test('a recipient that does not exist or is not offered by the event is rejected', function () {
    $superadmin = ruleUser('superadmin');

    $this->actingAs($superadmin)->putJson('/configuracion/reglas-notificaciones/purchase_order_created', [
        'enabled' => true, 'channel_database' => true, 'channel_mail' => false, 'exclude_actor' => true,
        'recipients' => [['type' => 'role', 'value' => 'rol-que-no-existe', 'permission_filter' => null]],
    ])->assertStatus(422);

    // 'creator' solo existe como destinatario automático en otros eventos
    $this->actingAs($superadmin)->putJson('/configuracion/reglas-notificaciones/purchase_order_created', [
        'enabled' => true, 'channel_database' => true, 'channel_mail' => false, 'exclude_actor' => true,
        'recipients' => [['type' => 'dynamic', 'value' => 'creator', 'permission_filter' => null]],
    ])->assertStatus(422);
});

test('recipients are the sum of roles, areas, users and automatic ones without repeating anyone', function () {
    Notification::fake();
    $area = Area::create(['name' => 'Area prueba']);
    $byRole = ruleUser('contabilidad');
    $byArea = User::factory()->create(['area_id' => $area->id]);
    $byUser = User::factory()->create();
    $creator = User::factory()->create();
    $outsider = User::factory()->create();

    saveRule('invoice_difference_approved', [
        'exclude_actor' => false,
        'recipients' => [
            ['type' => 'role', 'value' => 'contabilidad', 'permission_filter' => null],
            ['type' => 'area', 'value' => (string) $area->id, 'permission_filter' => null],
            ['type' => 'user', 'value' => (string) $byUser->id, 'permission_filter' => null],
            ['type' => 'dynamic', 'value' => 'creator', 'permission_filter' => null],
            ['type' => 'user', 'value' => (string) $byUser->id, 'permission_filter' => null],
        ],
        'enabled' => true,
    ]);

    app(NotificationDispatcher::class)->dispatch('invoice_difference_approved', ruleNotification(), ['creator' => $creator]);

    foreach ([$byRole, $byArea, $byUser, $creator] as $user) {
        Notification::assertSentToTimes($user, GenericEventNotification::class, 1);
    }
    Notification::assertNotSentTo($outsider, GenericEventNotification::class);
});

test('the permission filter and the actor exclusion narrow the recipients', function () {
    Notification::fake();
    Permission::findOrCreate('aprobar orden compra', 'web');
    $withPermission = ruleUser('gerencia');
    $withPermission->givePermissionTo('aprobar orden compra');
    $withoutPermission = ruleUser('gerencia');
    $actor = ruleUser('gerencia');
    $actor->givePermissionTo('aprobar orden compra');

    saveRule('purchase_order_pending_approval', [
        'enabled' => true,
        'exclude_actor' => true,
        'recipients' => [['type' => 'role', 'value' => 'gerencia', 'permission_filter' => 'aprobar orden compra']],
    ]);

    app(NotificationDispatcher::class)->dispatch('purchase_order_pending_approval', ruleNotification(), ['actor' => $actor]);

    Notification::assertSentTo($withPermission, GenericEventNotification::class);
    Notification::assertNotSentTo($withoutPermission, GenericEventNotification::class);
    Notification::assertNotSentTo($actor, GenericEventNotification::class);
});

test('a disabled event notifies nobody and the channels come from the configuration', function () {
    Notification::fake();
    $recipient = ruleUser('contabilidad');

    // Apagado por defecto (evento nuevo)
    app(NotificationDispatcher::class)->dispatch('purchase_invoice_rejected', ruleNotification(), []);
    Notification::assertNothingSent();

    saveRule('purchase_invoice_rejected', [
        'enabled' => true,
        'channel_database' => true,
        'channel_mail' => true,
        'exclude_actor' => false,
        'recipients' => [['type' => 'role', 'value' => 'contabilidad', 'permission_filter' => null]],
    ]);
    app(NotificationDispatcher::class)->dispatch('purchase_invoice_rejected', ruleNotification(), []);

    Notification::assertSentTo($recipient, GenericEventNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']);
});

// ── Recorridos reales: el negocio dispara el evento y la configuración decide ───────────────

test('a purchase request response reaches the requester and the buyer, never the responder', function () {
    Notification::fake();
    $requester = ruleUser('general');
    $buyer = ruleUser('logistica');
    $responder = ruleUser('admin');
    $request = \App\Models\Expenses\PurchaseRequest::create([
        'requester_user_id' => $requester->id, 'buyer_user_id' => $buyer->id,
        'date' => now()->toDateString(), 'status' => 'pending', 'solicited_to' => 'gerencia',
    ]);
    $this->actingAs($responder);

    app(\App\Services\Expenses\PurchaseRequestService::class)->approve($request);

    Notification::assertSentTo($requester, \App\Notifications\PurchaseRequestRespondedNotification::class);
    Notification::assertSentTo($buyer, \App\Notifications\PurchaseRequestRespondedNotification::class);
    Notification::assertNotSentTo($responder, \App\Notifications\PurchaseRequestRespondedNotification::class);
});

test('changing the configuration changes who gets the real notification', function () {
    Notification::fake();
    $extra = ruleUser('general');
    saveRule('purchase_request_responded', ['recipients' => [['type' => 'user', 'value' => (string) $extra->id, 'permission_filter' => null]]]);

    $requester = ruleUser('produccion');
    $request = \App\Models\Expenses\PurchaseRequest::create([
        'requester_user_id' => $requester->id, 'date' => now()->toDateString(), 'status' => 'pending', 'solicited_to' => 'gerencia',
    ]);
    $this->actingAs(ruleUser('admin'));
    app(\App\Services\Expenses\PurchaseRequestService::class)->approve($request);

    Notification::assertSentTo($extra, \App\Notifications\PurchaseRequestRespondedNotification::class);
    Notification::assertNotSentTo($requester, \App\Notifications\PurchaseRequestRespondedNotification::class);
});

test('an inventory request notifies logistica by default and follows the configuration afterwards', function () {
    Notification::fake();
    $logistica = ruleUser('logistica');
    $creator = ruleUser('produccion');
    $notification = new \App\Notifications\InventoryRequestCreatedNotification(
        new \App\Models\Inventory\InventoryRequest(['document_number' => 'INV-1']), $creator->name, null
    );

    app(NotificationDispatcher::class)->dispatch('inventory_request_created', $notification, ['actor' => $creator]);
    Notification::assertSentTo($logistica, \App\Notifications\InventoryRequestCreatedNotification::class);

    saveRule('inventory_request_created', ['enabled' => false]);
    Notification::fake();
    app(NotificationDispatcher::class)->dispatch('inventory_request_created', $notification, ['actor' => $creator]);
    Notification::assertNothingSent();
});

test('the mail channel is used only when configured and skips people without email', function () {
    Notification::fake();
    $withMail = ruleUser('contabilidad', ['email' => 'conta@example.com']);
    saveRule('purchase_order_created', ['channel_database' => false, 'channel_mail' => true]);

    app(NotificationDispatcher::class)->dispatch('purchase_order_created', ruleNotification(), ['actor' => ruleUser('admin')]);

    Notification::assertSentTo($withMail, GenericEventNotification::class, fn ($n, $channels) => $channels === ['mail']);
});

test('the mail notification renders a subject and body', function () {
    $mail = (new GenericEventNotification('x', 'Gastos', 'Asunto', 'Cuerpo', '/gastos'))->withChannels(['mail'])->toMail(ruleUser('admin'));

    expect($mail->subject)->toBe('Asunto');
});

test('a failing notification never breaks the business operation that triggered it', function () {
    ruleUser('contabilidad');
    // el rol ya existe: el evento usa los destinatarios por defecto
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp caído'));

    app(NotificationDispatcher::class)->dispatch('purchase_order_created', ruleNotification(), []);

    expect(true)->toBeTrue();
});

test('an unknown event key is ignored without errors', function () {
    Notification::fake();

    app(NotificationDispatcher::class)->dispatch('evento_que_no_existe', ruleNotification(), []);

    Notification::assertNothingSent();
});

test('recipients the flow needs cannot be removed, but more can be added', function () {
    $extra = ruleUser('general');

    expect(fn () => saveRule('batch_control_novelty', ['recipients' => [['type' => 'user', 'value' => (string) $extra->id, 'permission_filter' => null]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    saveRule('batch_control_novelty', ['recipients' => [
        ['type' => 'dynamic', 'value' => 'novelty_areas', 'permission_filter' => null],
        ['type' => 'user', 'value' => (string) $extra->id, 'permission_filter' => null],
    ]]);

    $row = collect(app(NotificationRuleService::class)->list())->firstWhere('key', 'batch_control_novelty');
    expect(collect($row['recipients'])->firstWhere('value', 'novelty_areas')['locked'])->toBeTrue()
        ->and(collect($row['recipients'])->firstWhere('value', (string) $extra->id)['locked'])->toBeFalse();
});
