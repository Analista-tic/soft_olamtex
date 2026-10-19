<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Tax;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Services\Finance\AccountingPostingService;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use Spatie\Permission\Models\Permission;

/*
 * Al aprobar la recepción de una factura de tela se contabiliza la factura (mismo posteo que receiveInvoice),
 * sin bloquear la recepción física si falta configuración contable.
 * Factura: 500 m a $100/m = $50.000 + IVA 19% ($9.500) = $59.500 (5 rollos de 100 m).
 */

function fraFabricInvoice(bool $withAccounts): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS RECEPCION CONTABLE TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela recepcion contable test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();
    Warehouse::firstOrCreate(['name' => 'Bodega recepcion contable tela'], ['observation' => 'Test']);

    $attributes = [
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela recepcion contable '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
        'tracks_inventory' => false,
    ];

    if ($withAccounts) {
        $account = fn (string $code, string $type, ?string $special = null) => AccountingAccount::firstOrCreate(
            ['code' => $code],
            ['name' => 'Cuenta '.$code, 'account_type' => $type, 'level' => 1, 'allows_entries' => true, 'is_active' => true, 'special_type' => $special]
        );
        $cost = $account('5135', AccountingAccount::TYPE_COST);
        $vat = $account('240810', AccountingAccount::TYPE_ASSET);
        $account('220505', AccountingAccount::TYPE_LIABILITY, AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE);
        $tax = Tax::firstOrCreate(['name' => 'IVA 19% recepcion test'], ['percentage' => 19, 'debit_account_id' => $vat->id, 'status' => true]);
        $attributes += ['tax_id' => $tax->id, 'cost_of_sale_account_id' => $cost->id];
    }

    $item = Item::create($attributes);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-RCT-'.uniqid(),
        'contact_id' => Contact::factory()->create()->id,
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => 500,
        'price' => 100,
        'tax' => 19,
        'total' => 50000,
        'total_tax' => 9500,
    ]);

    $service = app(FabricRollService::class);
    foreach (range(1, 5) as $ignored) {
        $service->storeFromReception([
            'purchase_document_item_id' => $line->id,
            'quantity' => 100,
            'roll_width' => 1.5,
            'entry_date' => now()->toDateString(),
        ]);
    }

    return [$invoice, $item];
}

function fraInvoiceEntries(PurchaseDocument $invoice)
{
    return JournalEntry::query()->forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(FabricRollService::class);
});

it('posts the fabric invoice exactly once when the reception is approved', function () {
    [$invoice, $item] = fraFabricInvoice(true);

    $approved = $this->service->approveReception($invoice->id);

    $entry = fraInvoiceEntries($invoice)->with('lines')->first();

    expect($approved->status)->toBe('approved')
        ->and($approved->getAttribute('accounting_warning'))->toBeNull()
        ->and(fraInvoiceEntries($invoice)->count())->toBe(1)
        ->and($entry->status)->toBe(JournalEntry::STATUS_POSTED)
        ->and((float) $entry->total_debit)->toBe(59500.0)
        ->and((float) $entry->total_credit)->toBe(59500.0)
        // Costo $50.000 + IVA $9.500 al debe; cuentas por pagar $59.500 al haber.
        ->and($entry->lines->count())->toBe(3);

    // Idempotente: reintentar el posteo (o llamar de nuevo a postPurchaseInvoice) devuelve el mismo asiento.
    $posting = app(AccountingPostingService::class);
    $again = $posting->postPurchaseInvoiceIfMissing($invoice->fresh());
    $direct = $posting->postPurchaseInvoice($invoice->fresh());

    expect($again->id)->toBe($entry->id)
        ->and($direct->id)->toBe($entry->id)
        ->and(fraInvoiceEntries($invoice)->count())->toBe(1);
});

it('does not double-account inventory: reception movements generate no journal entry of their own', function () {
    [$invoice, $item] = fraFabricInvoice(true);

    $this->service->approveReception($invoice->id);

    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(5)
        // El único asiento generado por toda la recepción es el de la factura.
        ->and(JournalEntry::count())->toBe(1)
        ->and(JournalEntry::where('source_type', AccountingPostingService::SOURCE_INVENTORY_ADJUSTMENT)->count())->toBe(0)
        ->and((float) JournalEntry::first()->total_debit)->toBe(59500.0);
});

it('does not block the reception when accounting is not configured and returns a warning', function () {
    [$invoice, $item] = fraFabricInvoice(false);

    $approved = $this->service->approveReception($invoice->id);

    expect($approved->status)->toBe('approved')
        ->and($approved->getAttribute('accounting_warning'))->toBeString()->toContain('no se pudo contabilizar')
        ->and(fraInvoiceEntries($invoice)->count())->toBe(0)
        ->and(JournalEntry::count())->toBe(0)
        // La recepción física y el inventario siguen adelante.
        ->and(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(5)
        ->and($invoice->fresh()->received_at)->not->toBeNull();
});

it('returns accounting_warning at the top level of the approve endpoint response', function () {
    foreach (['ver telas rollos', 'aprobar recepcion tela'] as $name) {
        $this->user->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    [$noAccounts] = fraFabricInvoice(false);
    $this->patchJson("/kardex-telas/recepcion/{$noAccounts->id}/aprobar")
        ->assertOk()
        ->assertJsonPath('message', 'Recepción aprobada correctamente')
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonMissingPath('data.accounting_warning')
        ->assertJson(fn ($json) => $json->where('accounting_warning', fn ($w) => is_string($w) && str_contains($w, 'no se pudo contabilizar'))->etc());

    [$withAccounts] = fraFabricInvoice(true);
    $this->patchJson("/kardex-telas/recepcion/{$withAccounts->id}/aprobar")
        ->assertOk()
        ->assertJsonPath('accounting_warning', null);

    expect(fraInvoiceEntries($withAccounts)->count())->toBe(1);
});
