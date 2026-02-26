import type {
    PurchaseInvoiceDto,
    PurchaseInvoiceItemDto,
    PurchaseInvoiceWithholdingDto,
    PurchaseOrderDto,
    PurchaseOrderItemDto,
    PurchaseRequestDto,
    PurchaseRequestItemDto,
} from './dto';
import type {
    PurchaseInvoice,
    PurchaseInvoiceItem,
    PurchaseInvoiceWithholding,
    PurchaseOrder,
    PurchaseOrderItem,
    PurchaseRequest,
    PurchaseRequestItem,
} from './types';

import { mapContactDto, type Item } from '@/domain/MasterData';

export function mapPurchaseOrderItemDto(dto: PurchaseOrderItemDto): PurchaseOrderItem {
    const rawItem = (dto as any).item;
    return {
        ...dto,
        item: rawItem && typeof rawItem === 'object' ? (rawItem as Item) : undefined,
    };
}

export function mapPurchaseOrderDto(dto: PurchaseOrderDto): PurchaseOrder {
    const allowed = ['pending', 'approved', 'sent', 'invoiced', 'rejected'];
    const status = typeof dto.status === 'string' && allowed.includes(dto.status) ? (dto.status as PurchaseOrder['status']) : undefined;

    return {
        ...dto,
        contact: dto.contact ? mapContactDto(dto.contact) : undefined,
        supplier: dto.supplier ? mapContactDto(dto.supplier) : undefined,
        items: dto.items.map(mapPurchaseOrderItemDto),
        status,
    };
}

export function mapPurchaseInvoiceWithholdingDto(dto: PurchaseInvoiceWithholdingDto): PurchaseInvoiceWithholding {
    return { ...dto };
}

export function mapPurchaseInvoiceItemDto(dto: PurchaseInvoiceItemDto): PurchaseInvoiceItem {
    const rawItem = (dto as any).item;
    return {
        ...dto,
        item: rawItem && typeof rawItem === 'object' ? (rawItem as Item) : undefined,
        original_order_item: dto.original_order_item ? mapPurchaseOrderItemDto(dto.original_order_item) : undefined,
    };
}

export function mapPurchaseInvoiceDto(dto: PurchaseInvoiceDto): PurchaseInvoice {
    const allowed = ['pending_receipt', 'received', 'approved', 'rejected'];
    const status = typeof dto.status === 'string' && allowed.includes(dto.status) ? (dto.status as PurchaseInvoice['status']) : undefined;

    return {
        ...dto,
        contact: dto.contact ? mapContactDto(dto.contact) : undefined,
        supplier: dto.supplier ? mapContactDto(dto.supplier) : undefined,
        items: dto.items.map(mapPurchaseInvoiceItemDto),
        withholdings: dto.withholdings?.map(mapPurchaseInvoiceWithholdingDto),
        status,
    };
}

export function mapPurchaseRequestItemDto(dto: PurchaseRequestItemDto): PurchaseRequestItem {
    const rawItem = (dto as any).item;
    return {
        ...dto,
        item: rawItem && typeof rawItem === 'object' ? (rawItem as Item) : undefined,
    };
}

export function mapPurchaseRequestDto(dto: PurchaseRequestDto): PurchaseRequest {
    return {
        ...dto,
        provider_name: dto.provider ? dto.provider.full_name : undefined,
        requester: dto.requester ?? undefined,
        items: dto.items?.map(mapPurchaseRequestItemDto),
    } as PurchaseRequest;
}
