<?php

use App\Http\Controllers\Expenses\PurchaseInvoiceController;
use App\Http\Controllers\Expenses\PurchaseInvoiceTelaController;
use App\Http\Controllers\Expenses\PurchaseOrderController;
use App\Http\Controllers\Expenses\PurchasePaymentController;
use App\Http\Controllers\Expenses\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

// === GASTOS (AGRUPADO) ===
Route::prefix('gastos')->name('gastos.')->group(function () {
    // === SOLICITUD DE COMPRA ===
    Route::prefix('solicitudes-compra')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::get('/data', [PurchaseRequestController::class, 'getData'])->name('getData');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('show');
        Route::put('/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('update');
        Route::delete('/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('destroy');

        // Acciones de transición de estados
        Route::post('/{purchaseRequest}/send-to-approval', [PurchaseRequestController::class, 'sendToApproval'])
            ->middleware('permission:aprobar solicitudes compra')
            ->name('send-to-approval');
        Route::post('/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])
            ->middleware('permission:aprobar solicitudes compra')
            ->name('approve');
        Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
            ->middleware('permission:rechazar solicitudes compra')
            ->name('reject');
        Route::post('/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])
            ->middleware('permission:cancelar solicitudes compra')
            ->name('cancel');
    });

    // === ORDENES DE COMPRA ===
    Route::prefix('ordenes-compra')->name('purchase-orders.')->middleware('permission:ver ordenes compra')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/data', [PurchaseOrderController::class, 'getData'])->name('getData');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store')->middleware('permission:crear ordenes compra');
        Route::get('/{purchaseDocument}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::put('/{purchaseDocument}', [PurchaseOrderController::class, 'update'])->name('update')->middleware('permission:editar ordenes compra');
        Route::delete('/{purchaseDocument}', [PurchaseOrderController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar ordenes compra');
        Route::post('/{purchaseDocument}/enviar-correo-pdf', [PurchaseOrderController::class, 'sendOrderPdf'])
            ->name('send-pdf')
            ->middleware('permission:editar ordenes compra');
        Route::post('/{purchaseDocument}/convert-to-invoice', [PurchaseOrderController::class, 'convertToInvoice'])
            ->name('convert-to-invoice')
            ->middleware('permission:editar ordenes compra');
        Route::patch('/{purchaseDocument}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('approve')
            ->middleware('permission:aprobar orden compra');
    });

    // === FACTURAS DE COMPRA ===
    Route::prefix('facturas-compra')->name('purchase-invoices.')->middleware('permission:ver facturas compra')->group(function () {
        Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
        Route::get('/data', [PurchaseInvoiceController::class, 'getData'])->name('getData');
        Route::get('/{purchaseDocument}/notas-credito-debito', [PurchaseInvoiceController::class, 'creditDebitNotes'])->name('credit-debit-notes');
        Route::get('/{purchaseDocument}/notes-summary', [PurchaseInvoiceController::class, 'notesSummary'])->name('notes-summary');
        Route::post('/', [PurchaseInvoiceController::class, 'store'])->name('store')->middleware('permission:crear facturas compra');
        Route::get('/{purchaseDocument}', [PurchaseInvoiceController::class, 'show'])->name('show');
        Route::put('/{purchaseDocument}', [PurchaseInvoiceController::class, 'update'])->name('update')->middleware('permission:editar facturas compra');
        Route::delete('/{purchaseDocument}', [PurchaseInvoiceController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar facturas compra');
        Route::post('/{purchaseDocument}/justificar', [PurchaseInvoiceController::class, 'justify'])->name('justify')->middleware('permission:editar facturas compra');
        Route::post('/{purchaseDocument}/aprobar-diferencia', [PurchaseInvoiceController::class, 'approveDifference'])->name('approve-difference')->middleware('permission:gestionar diferencias factura compra|aprobar facturas compra');
        Route::post('/{purchaseDocument}/rechazar-diferencia', [PurchaseInvoiceController::class, 'rejectDifference'])->name('reject-difference')->middleware('permission:gestionar diferencias factura compra|aprobar facturas compra');
        Route::patch('/{purchaseDocument}/recibir', [PurchaseInvoiceController::class, 'receive'])->name('receive')->middleware('permission:recibir factura compra');
        Route::patch('/{purchaseDocument}/rechazar', [PurchaseInvoiceController::class, 'reject'])->name('reject')->middleware('permission:anular factura compra');
    });

    // === CUENTAS POR PAGAR (LEGACY DESCONTINUADO) ===
    Route::redirect('/cuentas-por-pagar', '/finanzas/comprobantes-egreso')
        ->name('accounts-payable.index');

    // === PAGOS DE FACTURAS (LEGACY TRANSITORIO) ===
    Route::prefix('pagos')->name('payments.')->group(function () {
        Route::get('/facturas/{purchaseDocument}', [PurchasePaymentController::class, 'byInvoice'])->name('byInvoice');
        Route::post('/', [PurchasePaymentController::class, 'store'])->name('store');
    });

    // === FACTURAS DE COMPRA TELAS ===
    Route::prefix('facturas-compra-tela')->name('purchase-invoices-fabric.')->middleware('permission:ver facturas compra')->group(function () {
        Route::get('/', [PurchaseInvoiceTelaController::class, 'indexFabric'])->name('index');
        Route::get('/data', [PurchaseInvoiceTelaController::class, 'getData'])->name('getData');
        Route::post('/', [PurchaseInvoiceTelaController::class, 'store'])->name('store')->middleware('permission:crear facturas compra');
        Route::get('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'show'])->name('show');
        Route::put('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'update'])->name('update')->middleware('permission:editar facturas compra');
        Route::delete('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar facturas compra');
        Route::post('/{purchaseInvoice}/justificar', [PurchaseInvoiceTelaController::class, 'justify'])->name('justify')->middleware('permission:editar facturas compra');
        Route::post('/{purchaseInvoice}/aprobar-diferencia', [PurchaseInvoiceTelaController::class, 'approveDifference'])->name('approve-difference')->middleware('permission:gestionar diferencias factura compra|aprobar facturas compra');
        Route::post('/{purchaseInvoice}/rechazar-diferencia', [PurchaseInvoiceTelaController::class, 'rejectDifference'])->name('reject-difference')->middleware('permission:gestionar diferencias factura compra|aprobar facturas compra');
    });
});
