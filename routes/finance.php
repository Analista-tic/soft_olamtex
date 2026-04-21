<?php

use App\Http\Controllers\Finance\AccountingAccountController;
use App\Http\Controllers\Finance\BankAccountController;
use App\Http\Controllers\Finance\CreditDebitNoteController;
use App\Http\Controllers\Finance\RetentionController;
use App\Http\Controllers\Finance\TaxController;
use App\Http\Controllers\Finance\TreasuryLookupController;
use App\Http\Controllers\Expenses\ExpenseVoucherController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// === RETENCIONES ===
Route::get('retenciones/data', [RetentionController::class, 'data'])->name('retenciones.data');

Route::prefix('retenciones')->name('retenciones.')->middleware('permission:ver retenciones')->group(function () {
    Route::get('/', [RetentionController::class, 'index'])->name('index');
    Route::get('/{id}', [RetentionController::class, 'show'])->name('show');
    Route::post('/', [RetentionController::class, 'store'])->name('store')->middleware('permission:crear retenciones');
    Route::put('/{id}', [RetentionController::class, 'update'])->name('update')->middleware('permission:editar retenciones');
    Route::delete('/{id}', [RetentionController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar retenciones');
});

// === IMPUESTOS ===
Route::get('impuestos/data', [TaxController::class, 'data'])->name('impuestos.data');

Route::prefix('impuestos')->name('impuestos.')->middleware('permission:ver impuestos')->group(function () {
    Route::get('/', [TaxController::class, 'index'])->name('index');
    Route::get('/{id}', [TaxController::class, 'show'])->name('show');
    Route::post('/', [TaxController::class, 'store'])->name('store')->middleware('permission:crear impuestos');
    Route::put('/{id}', [TaxController::class, 'update'])->name('update')->middleware('permission:editar impuestos');
    Route::delete('/{id}', [TaxController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar impuestos');
    Route::post('/{id}/toggle-status', [TaxController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:editar impuestos');
});

// === NOTAS CRÉDITO / DÉBITO ===
Route::prefix('notas-credito-debito')->name('notas-credito-debito.')->middleware('permission:ver notas credito debito')->group(function () {
    Route::get('/', [CreditDebitNoteController::class, 'index'])->name('index');
    Route::get('/data', [CreditDebitNoteController::class, 'getData'])->name('data');
    Route::get('/next-number', [CreditDebitNoteController::class, 'nextNumber'])->name('next-number');
    Route::get('/eligible-purchase-invoices', [CreditDebitNoteController::class, 'eligiblePurchaseInvoices'])->name('eligible-purchase-invoices');
    Route::post('/', [CreditDebitNoteController::class, 'store'])->name('store')->middleware('permission:crear notas credito debito');
    Route::get('/{id}', [CreditDebitNoteController::class, 'show'])->name('show');
    Route::put('/{id}', [CreditDebitNoteController::class, 'update'])->name('update')->middleware('permission:editar notas credito debito');
    Route::delete('/{id}', [CreditDebitNoteController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar notas credito debito');
});

// === FINANZAS: TESORERÍA ===
Route::prefix('finanzas')->name('finanzas.')->group(function () {
    // Comprobantes de egreso
    Route::get('/comprobantes-egreso', [ExpenseVoucherController::class, 'index'])->name('expense-vouchers.index')->middleware('permission:ver comprobantes egreso');
    Route::get('/comprobantes-egreso/data', [ExpenseVoucherController::class, 'data'])->name('expense-vouchers.data')->middleware('permission:ver comprobantes egreso');
    Route::get('/comprobantes-egreso/crear', [ExpenseVoucherController::class, 'create'])->name('expense-vouchers.create')->middleware('permission:crear comprobantes egreso');
    Route::get('/comprobantes-egreso/{voucher}/editar', [ExpenseVoucherController::class, 'edit'])->name('expense-vouchers.edit')->middleware('permission:editar comprobantes egreso');
    Route::post('/comprobantes-egreso/{voucher}/anular', [ExpenseVoucherController::class, 'void'])->name('expense-vouchers.void')
        ->middleware('permission:anular comprobantes egreso');
    Route::get('/comprobantes-egreso/{voucher}', [ExpenseVoucherController::class, 'show'])->name('expense-vouchers.show')->middleware('permission:ver comprobantes egreso');
    Route::post('/comprobantes-egreso', [ExpenseVoucherController::class, 'store'])->name('expense-vouchers.store')->middleware('permission:crear comprobantes egreso');
    Route::put('/comprobantes-egreso/{voucher}', [ExpenseVoucherController::class, 'update'])->name('expense-vouchers.update')->middleware('permission:editar comprobantes egreso');

    // Recibos de caja
    Route::get('/recibos-caja', fn () => Inertia::render('Finance/CashReceipts/Index'))
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.index');
    Route::get('/recibos-caja/data', [\App\Http\Controllers\Finance\CashReceiptController::class, 'index'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.data');
    Route::get('/recibos-caja/crear', fn () => Inertia::render('Finance/CashReceipts/CashReceiptForm'))
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.create');
    Route::post('/recibos-caja', [\App\Http\Controllers\Finance\CashReceiptController::class, 'store'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.store');
    Route::get('/recibos-caja/pending-invoices', [\App\Http\Controllers\Finance\CashReceiptController::class, 'pendingInvoices'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.pending-invoices');
    Route::get('/recibos-caja/customers', [\App\Http\Controllers\Finance\CashReceiptController::class, 'customers'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.customers');
    Route::get('/recibos-caja/accounts', [\App\Http\Controllers\Finance\CashReceiptController::class, 'accounts'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.accounts');
    Route::get('/recibos-caja/payment-methods', [\App\Http\Controllers\Finance\CashReceiptController::class, 'paymentMethods'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.payment-methods');
    Route::get('/recibos-caja/{receipt}', [\App\Http\Controllers\Finance\CashReceiptController::class, 'show'])
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.show');
    Route::post('/recibos-caja/{receipt}/void', [\App\Http\Controllers\Finance\CashReceiptController::class, 'void'])
        ->middleware('permission:anular recibo caja')
        ->name('cash-receipts.void');

    // === CATÁLOGO DE CUENTAS ===
    Route::prefix('catalogo-cuentas')->name('account-chart.')->middleware('permission:ver catalogo cuentas')->group(function () {
        Route::get('/', [AccountingAccountController::class, 'index'])->name('index');
        Route::get('/crear', [AccountingAccountController::class, 'create'])->name('create')->middleware('permission:crear catalogo cuentas');
        Route::get('/data', [AccountingAccountController::class, 'data'])->name('data');
        Route::get('/options', [AccountingAccountController::class, 'options'])->name('options');
        Route::get('/{id}/data', [AccountingAccountController::class, 'item'])->name('item');
        Route::get('/{id}/movimientos', [AccountingAccountController::class, 'movements'])
            ->middleware('permission:ver movimientos cuenta contable')
            ->name('movements');
        Route::get('/{id}/movimientos/data', [AccountingAccountController::class, 'movementsData'])
            ->middleware('permission:ver movimientos cuenta contable')
            ->name('movements.data');
        Route::get('/{id}/editar', [AccountingAccountController::class, 'edit'])->name('edit')->middleware('permission:editar catalogo cuentas');
        Route::get('/{id}', [AccountingAccountController::class, 'show'])->name('show');
        Route::post('/', [AccountingAccountController::class, 'store'])->name('store')->middleware('permission:crear catalogo cuentas');
        Route::put('/{id}', [AccountingAccountController::class, 'update'])->name('update')->middleware('permission:editar catalogo cuentas');
        Route::post('/{id}/toggle-status', [AccountingAccountController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:editar catalogo cuentas');
    });

    // Tesorería lookups
    Route::prefix('tesoreria')->name('treasury.')->group(function () {
        Route::get('/proveedores', [TreasuryLookupController::class, 'suppliers'])->name('suppliers');
        Route::get('/clientes', [TreasuryLookupController::class, 'customers'])->name('customers');
        Route::get('/documentos-por-pagar', [TreasuryLookupController::class, 'payableDocuments'])->name('payable-documents');
        Route::get('/documentos-por-cobrar', [TreasuryLookupController::class, 'receivableDocuments'])->name('receivable-documents');
        Route::get('/cuentas', [TreasuryLookupController::class, 'accounts'])->name('accounts');
        Route::get('/metodos-pago', [TreasuryLookupController::class, 'paymentMethods'])->name('payment-methods');
    });

    // === BANCOS Y CAJAS ===
    Route::prefix('bancos')->name('bancos.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::post('/', [BankAccountController::class, 'store'])->name('store');
        Route::get('/{bankAccount}', [BankAccountController::class, 'show'])->name('show');
        Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
        Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
    });
});
