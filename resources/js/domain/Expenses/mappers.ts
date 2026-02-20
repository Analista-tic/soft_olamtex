import type {
    AdditionalContact,
    Contact,
    PurchaseInvoice,
    PurchaseInvoiceItem,
    PurchaseInvoiceWithholding,
    PurchaseOrder,
    PurchaseOrderItem,
    PurchaseRequest,
    PurchaseRequestItem,
} from './types';
import type {
    AdditionalContactDto,
    ContactDto,
    PurchaseInvoiceDto,
    PurchaseInvoiceItemDto,
    PurchaseInvoiceWithholdingDto,
    PurchaseOrderDto,
    PurchaseOrderItemDto,
    PurchaseRequestDto,
    PurchaseRequestItemDto,
} from './dto';

import type { Item } from '@/domain/MasterData';

function mapAdditionalContactDto(dto: AdditionalContactDto): AdditionalContact {
    return {
        name: dto.name,
        phone: dto.phone,
        email: dto.email,
    };
}

export function mapContactDto(dto: ContactDto): Contact {
    return {
        id: dto.id,
        contact_type: dto.contact_type,
        id_type: dto.id_type,
        id_number: dto.id_number,
        dv: dto.dv,
        full_name: dto.full_name,
        person_type: dto.person_type,
        category: dto.category,
        municipality: dto.municipality,
        department: dto.department,
        address: dto.address,
        email: dto.email,
        phone: dto.phone,
        additional_contacts: dto.additional_contacts?.map(mapAdditionalContactDto),
        groups: dto.groups,
        created_at: dto.created_at,
        updated_at: dto.updated_at,
    };
}

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
