<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ValidatesPurchaseInvoiceRequest;
use App\Services\Expenses\PurchaseInvoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    use ValidatesPurchaseInvoiceRequest;

    public function __construct(protected PurchaseInvoiceService $service) {}

    /**
     * Muestra la vista principal de facturas de compra.
     */
    public function index()
    {
        return Inertia::render('Expenses/PurchaseInvoices/Index');
    }

    /**
     * Muestra una factura de compra específica.
     */
    public function show($purchaseInvoice)
    {
        return response()->json([
            'data' => $this->service->findWithRelations($purchaseInvoice),
        ]);
    }

    /**
     * Valida y almacena una nueva factura de compra.
     */
    public function store(Request $request)
    {
        $data = $this->validateInvoiceRequest($request);

        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }

        $invoice = $this->service->store($data, false);

        return response()->json(['data' => $invoice], 201);
    }

    /**
     * Valida y actualiza una factura de compra existente.
     */
    public function update(Request $request, $id)
    {
        $invoice = $this->service->findWithRelations($id);
        $data = $this->validateInvoiceRequest($request, true);

        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }

        $updated = $this->service->update($invoice, $data, false);

        return response()->json(['data' => $updated]);
    }

    /**
     * Elimina una factura de compra y sus items asociados.
     */
    public function destroy($id)
    {
        $invoice = $this->service->findWithRelations($id);
        $this->service->delete($invoice);

        return response()->json(['message' => 'Factura eliminada']);
    }

    /**
     * Devuelve datos paginados de facturas de compra según filtros.
     */
    public function getData(Request $request)
    {
        $filters = $this->buildFilters($request);
        $perPage = $request->per_page ?? 20;
        $invoices = $this->service->paginate($filters, $perPage, false);

        return response()->json([
            'data' => $invoices->items(),
            'total' => $invoices->total(),
            'current_page' => $invoices->currentPage(),
        ]);
    }

    private function buildFilters(Request $request): array
    {
        return $request->only(['search', 'date_from', 'date_to', 'status', 'provider_id']);
    }
}
