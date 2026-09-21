<?php

use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use App\Services\Expenses\PurchaseRequestService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Destinatarios al enviar una solicitud de compra: solo quienes pueden aprobar
 * (permiso 'aprobar solicitudes compra') en el área destino (gerencia -> admin, logistica -> logistica).
 * Un auxiliar sin el permiso o el propio solicitante no reciben el aviso.
 */
function makePurchaseRequest(User $requester, string $solicitedTo): PurchaseRequest
{
    return PurchaseRequest::create([
        'requester_user_id' => $requester->id,
        'date' => now()->toDateString(),
        'status' => 'pending',
        'solicited_to' => $solicitedTo,
    ]);
}

function makeUserWithRole(string $role, bool $canApprove): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    if ($canApprove) {
        $user->givePermissionTo(Permission::findOrCreate('aprobar solicitudes compra', 'web'));
    }

    return $user;
}

it('notifies only logistica users who can approve, not the auxiliar', function () {
    $requester = User::factory()->create();
    $leader = makeUserWithRole('logistica', true);
    makeUserWithRole('logistica', false); // auxiliar sin permiso de aprobar
    makeUserWithRole('admin', true);      // gerencia: no aplica a una solicitud a logística

    $recipients = app(PurchaseRequestService::class)->resolveSubmittedRecipients(makePurchaseRequest($requester, 'logistica'));

    expect($recipients->pluck('id')->all())->toBe([$leader->id]);
});

it('notifies only admins who can approve when it is addressed to gerencia', function () {
    $requester = User::factory()->create();
    $manager = makeUserWithRole('admin', true);
    makeUserWithRole('logistica', true);

    $recipients = app(PurchaseRequestService::class)->resolveSubmittedRecipients(makePurchaseRequest($requester, 'gerencia'));

    expect($recipients->pluck('id')->all())->toBe([$manager->id]);
});

it('never notifies the requester about their own request', function () {
    $requester = makeUserWithRole('admin', true);

    $recipients = app(PurchaseRequestService::class)->resolveSubmittedRecipients(makePurchaseRequest($requester, 'gerencia'));

    expect($recipients)->toBeEmpty();
});
