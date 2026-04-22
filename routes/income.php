<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// === INGRESOS ===
Route::prefix('income')->name('income.')->group(function () {
    // Cotizaciones
    Route::get('quotes', fn () => Inertia::render('Income/Quotes/Index'))
        ->middleware('permission:ver propios cotizaciones|ver area cotizaciones|ver todos cotizaciones')
        ->name('quotes.index');
    Route::get('quotes/data', [\App\Http\Controllers\Income\QuoteController::class, 'index'])
        ->middleware('permission:ver propios cotizaciones|ver area cotizaciones|ver todos cotizaciones')
        ->name('quotes.data');
    Route::post('quotes', [\App\Http\Controllers\Income\QuoteController::class, 'store'])
        ->middleware('permission:crear cotizaciones')
        ->name('quotes.store');
    Route::get('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'show'])
        ->middleware('permission:ver propios cotizaciones|ver area cotizaciones|ver todos cotizaciones')
        ->name('quotes.show');
    Route::put('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'update'])
        ->middleware('permission:editar cotizaciones')
        ->name('quotes.update');
    Route::delete('quotes/{saleDocument}', [\App\Http\Controllers\Income\QuoteController::class, 'destroy'])
        ->middleware('permission:eliminar cotizaciones')
        ->name('quotes.destroy');
    Route::post('quotes/{quote}/convert', [\App\Http\Controllers\Income\QuoteController::class, 'convert'])
        ->middleware('permission:convertir cotizacion a pedido')
        ->name('quotes.convert');

    // Pedidos de venta
    Route::get('sales-orders', fn () => Inertia::render('Income/SalesOrders/Index'))
        ->middleware('permission:ver propios pedidos venta|ver area pedidos venta|ver todos pedidos venta')
        ->name('sales-orders.index');
    Route::get('sales-orders/data', [\App\Http\Controllers\Income\SalesOrderController::class, 'index'])
        ->middleware('permission:ver propios pedidos venta|ver area pedidos venta|ver todos pedidos venta')
        ->name('sales-orders.data');
    Route::post('sales-orders', [\App\Http\Controllers\Income\SalesOrderController::class, 'store'])
        ->middleware('permission:crear pedidos venta')
        ->name('sales-orders.store');
    Route::get('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'show'])
        ->middleware('permission:ver propios pedidos venta|ver area pedidos venta|ver todos pedidos venta')
        ->name('sales-orders.show');
    Route::put('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'update'])
        ->middleware('permission:editar pedidos venta')
        ->name('sales-orders.update');
    Route::delete('sales-orders/{saleDocument}', [\App\Http\Controllers\Income\SalesOrderController::class, 'destroy'])
        ->middleware('permission:eliminar pedidos venta')
        ->name('sales-orders.destroy');
    Route::post('sales-orders/{salesOrder}/convert', [\App\Http\Controllers\Income\SalesOrderController::class, 'convert'])
        ->middleware('permission:convertir pedido a factura venta')
        ->name('sales-orders.convert');

    // Facturas de venta
    Route::get('sales-invoices', fn () => Inertia::render('Income/SalesInvoices/Index'))
        ->middleware('permission:ver propios facturas venta|ver area facturas venta|ver todos facturas venta')
        ->name('sales-invoices.index');
    Route::get('sales-invoices/data', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'index'])
        ->middleware('permission:ver propios facturas venta|ver area facturas venta|ver todos facturas venta')
        ->name('sales-invoices.data');
    Route::post('sales-invoices', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'store'])
        ->middleware('permission:crear facturas venta')
        ->name('sales-invoices.store');
    Route::get('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'show'])
        ->middleware('permission:ver propios facturas venta|ver area facturas venta|ver todos facturas venta')
        ->name('sales-invoices.show');
    Route::put('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'update'])
        ->middleware('permission:editar facturas venta')
        ->name('sales-invoices.update');
    Route::delete('sales-invoices/{saleDocument}', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'destroy'])
        ->middleware('permission:eliminar facturas venta')
        ->name('sales-invoices.destroy');
    Route::post('sales-invoices/{salesInvoice}/emit', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'emit'])
        ->middleware('permission:emitir factura venta')
        ->name('sales-invoices.emit');
    Route::post('sales-invoices/{salesInvoice}/void', [\App\Http\Controllers\Income\SalesInvoiceController::class, 'void'])
        ->middleware('permission:anular factura venta')
        ->name('sales-invoices.void');

    // Notas crédito/débito de venta
    Route::get('credit-debit-notes', fn () => Inertia::render('Income/CreditDebitNotes/Index'))
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.index');
    Route::get('credit-debit-notes/data', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'index'])
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.data');
    Route::post('credit-debit-notes', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'store'])
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.store');
    Route::get('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'show'])
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.show');
    Route::put('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'update'])
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.update');
    Route::delete('credit-debit-notes/{saleDocument}', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'destroy'])
        ->middleware('permission:ver ingresos')
        ->name('credit-debit-notes.destroy');
    Route::post('credit-debit-notes/{note}/emit', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'emit'])
        ->middleware('permission:emitir nota credito debito venta')
        ->name('credit-debit-notes.emit');
    Route::post('credit-debit-notes/{note}/void', [\App\Http\Controllers\Income\CreditDebitNoteController::class, 'void'])
        ->middleware('permission:anular nota credito debito venta')
        ->name('credit-debit-notes.void');

    // Recibos de caja
    Route::get('cash-receipts', fn () => Inertia::render('Income/CashReceipts/Index'))
        ->middleware('permission:ver propios recibos caja|ver todos recibos caja')
        ->name('cash-receipts.index');
});
