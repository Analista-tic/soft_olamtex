<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\WarehouseTransfer;
use App\Services\Inventory\WarehouseTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WarehouseTransferController extends Controller
{
    public function __construct(protected WarehouseTransferService $service) {}

    public function index()
    {
        return Inertia::render('Configuration/Warehouse/WarehouseTransfer/index');
    }

    public function getData(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $search = trim((string) $request->input('search', ''));

        $query = WarehouseTransfer::with(['originWarehouse', 'destinationWarehouse', 'items.item']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        $transfers = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(max($perPage, 1));

        return response()->json($transfers);
    }

    public function show(WarehouseTransfer $transfer)
    {
        $transfer->load(['originWarehouse', 'destinationWarehouse', 'items.item']);

        return response()->json($transfer);
    }

    public function store(Request $request)
    {
        $this->decodeItemsJson($request);

        try {
            $data = $this->validateTransfer($request);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }

        try {
            $transfer = $this->service->create($data, $request->user());

            return response()->json([
                'message' => 'Transferencia guardada correctamente.',
                'data' => $transfer,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Ocurrio un error al guardar la transferencia.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, WarehouseTransfer $transfer)
    {
        $this->decodeItemsJson($request);

        try {
            $data = $this->validateTransfer($request);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }

        try {
            $updated = $this->service->update($transfer, $data, $request->user());

            return response()->json([
                'message' => 'Transferencia actualizada correctamente.',
                'data' => $updated,
            ]);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Ocurrio un error al actualizar la transferencia.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, WarehouseTransfer $transfer)
    {
        try {
            $this->service->deleteTransfer($transfer, $request->user());

            return response()->json(['message' => 'Transferencia eliminada correctamente.']);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'No se pudo eliminar la transferencia.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    private function decodeItemsJson(Request $request): void
    {
        if ($request->has('items') && is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['items' => $decoded]);
            }
        }
    }

    private function validateTransfer(Request $request): array
    {
        $rules = [
            'origin_warehouse_id' => 'required|integer|exists:warehouses,id',
            'destination_warehouse_id' => 'required|integer|exists:warehouses,id|different:origin_warehouse_id',
            'date' => 'required|date',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ];

        return $request->validate($rules);
    }

    private function validationErrorResponse(ValidationException $exception)
    {
        return response()->json([
            'message' => 'Errores de validacion',
            'errors' => $exception->errors(),
        ], 422);
    }
}
