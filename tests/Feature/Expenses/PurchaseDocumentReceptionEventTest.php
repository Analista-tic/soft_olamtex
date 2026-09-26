<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentReceptionEvent;
use App\Models\Settings\User;

it('keeps a history of reception events per invoice, newest first', function () {
    $user = User::factory()->create();
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-EVT-'.uniqid(),
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);

    $invoice->receptionEvents()->create([
        'event_type' => PurchaseDocumentReceptionEvent::TYPE_REJECTED,
        'reason' => 'Mercancía dañada',
        'user_id' => $user->id,
        'occurred_at' => now()->subHour(),
    ]);
    $invoice->receptionEvents()->create([
        'event_type' => PurchaseDocumentReceptionEvent::TYPE_REOPENED,
        'reason' => 'El proveedor repuso la mercancía',
        'user_id' => $user->id,
        'occurred_at' => now(),
    ]);

    $events = $invoice->fresh()->load('receptionEvents.user')->receptionEvents;

    expect($events)->toHaveCount(2)
        ->and($events->first()->event_type)->toBe('reopened')
        ->and($events->last()->event_type)->toBe('rejected')
        ->and($events->first()->user->is($user))->toBeTrue();
});

it('deletes the events together with the invoice', function () {
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-EVT-'.uniqid(),
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $invoice->receptionEvents()->create([
        'event_type' => PurchaseDocumentReceptionEvent::TYPE_REJECTED,
        'reason' => 'Prueba',
        'occurred_at' => now(),
    ]);

    $invoice->forceDelete();

    expect(PurchaseDocumentReceptionEvent::count())->toBe(0);
});
