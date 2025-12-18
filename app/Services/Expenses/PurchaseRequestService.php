<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestService
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = PurchaseRequest::with(['provider', 'requester']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($sub) use ($search) {
                $sub->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('provider', fn($provider) =>
                        $provider->where('full_name', 'like', "%{$search}%")
                    );
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider_id']) && $filters['provider_id'] !== 'all') {
            $query->where('contact_id', $filters['provider_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function create(array $data): PurchaseRequest
    {
        $payload = $this->prepareData($data);
        return PurchaseRequest::create($payload)->load('provider', 'requester');
    }

    public function update(PurchaseRequest $purchaseRequest, array $data): PurchaseRequest
    {
        $payload = $this->prepareData($data, true);
        $purchaseRequest->update($payload);
        return $purchaseRequest->fresh('provider', 'requester');
    }

    public function delete(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->delete();
    }

    public function loadWithRelations(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->load(['provider', 'requester']);
    }

    protected function prepareData(array $data, bool $isUpdate = false): array
    {
        if (!$isUpdate && empty($data['request_number'])) {
            $data['request_number'] = $this->generateRequestNumber();
        }

        $data['status'] = $data['status'] ?? 'draft';
        $data['requester_user_id'] = $data['requester_user_id'] ?? auth()->id();

        return $data;
    }

    protected function generateRequestNumber(): string
    {
        $nextId = (int) PurchaseRequest::withTrashed()->max('id') + 1;
        return sprintf('SOL-%03d', $nextId);
    }
}
