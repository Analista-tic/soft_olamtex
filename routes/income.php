<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// === INGRESOS ===
Route::prefix('income')->name('income.')->group(function () {
    // Cotizaciones
    Route::get('quotes', fn () => Inertia::render('Income/Quotes/Index'))->name('quotes.index');
    Route::get('quotes/data', [\App\Http\Controllers\Income\QuoteController::class, 'index'])->name('quotes.data');
    Route::post('quotes', [\App\Http\Controllers\Income\QuoteController::class, 'store'])->name('quotes.store');
    Route::get('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'show'])->name('quotes.show');
    Route::put('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'update'])->name('quotes.update');
    Route::delete('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('quotes/{quote}/convert', [\App\Http\Controllers\Income\QuoteController::class, 'convert'])->name('quotes.convert');

    // Pedidos de venta
    Route::get('sales-orders', fn () => Inertia::render('Income/SalesOrders/Index'))->name('sales-orders.index');
    Route::get('sales-orders/data', [\App\Http\Controllers\Income\SalesOrderController::class, 'index'])->name('sales-orders.data');
    Route::post('sales-orders', [\App\Http\Controllers\Income\SalesOrderController::class, 'store'])->name('sales-orders.store');
    Route::get('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'show'])->name('sales-orders.show');
    Route::put('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'update'])->name('sales-orders.update');
    Route::delete('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'destroy'])->name('sales-orders.destroy');
    Route::post('sales-orders/{salesOrder}/convert', [\App\Http\Controllers\Income\SalesOrderController::class, 'convert'])->name('sales-orders.convert');

    // Facturas de venta
    Route::get('sales-invoices', fn () => Inertia::render('Income/SalesInvoices/Index'))->name('sales-invoices.index');
    Route::get('sales-invoices/data', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'index'])->name('sales-invoices.data');
    Route::post('sales-invoices', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'store'])->name('sales-invoices.store');
    Route::get('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'show'])->name('sales-invoices.show');
    Route::put('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'update'])->name('sales-invoices.update');
    Route::delete('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'destroy'])->name('sales-invoices.destroy');
    Route::post('sales-invoices/{salesInvoice}/emit', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'emit'])->name('sales-invoices.emit');
    Route::post('sales-invoices/{salesInvoice}/void', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'void'])->name('sales-invoices.void');

    // Notas crédito/débito de venta
    Route::get('credit-debit-notes', fn () => Inertia::render('Income/CreditDebitNotes/Index'))->name('credit-debit-notes.index');
    Route::get('credit-debit-notes/data', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'index'])->name('credit-debit-notes.data');
    Route::post('credit-debit-notes', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'store'])->name('credit-debit-notes.store');
    Route::get('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'show'])->name('credit-debit-notes.show');
    Route::put('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'update'])->name('credit-debit-notes.update');
    Route::delete('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'destroy'])->name('credit-debit-notes.destroy');
    Route::get('cash-receipts', fn () => Inertia::render('Income/CashReceipts/Index'))->name('cash-receipts.index');
    Route::post('credit-debit-notes/{note}/emit', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'emit'])->name('credit-debit-notes.emit');
    Route::post('credit-debit-notes/{note}/void', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'void'])->name('credit-debit-notes.void');
});
