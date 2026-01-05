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
    return { ...dto };
}

export function mapPurchaseOrderDto(dto: PurchaseOrderDto): PurchaseOrder {
    return {
        ...dto,
        contact: dto.contact ? mapContactDto(dto.contact) : undefined,
        supplier: dto.supplier ? mapContactDto(dto.supplier) : undefined,
        items: dto.items.map(mapPurchaseOrderItemDto),
    };
}

export function mapPurchaseInvoiceWithholdingDto(dto: PurchaseInvoiceWithholdingDto): PurchaseInvoiceWithholding {
    return { ...dto };
}

export function mapPurchaseInvoiceItemDto(dto: PurchaseInvoiceItemDto): PurchaseInvoiceItem {
    return {
        ...dto,
        original_order_item: dto.original_order_item ? mapPurchaseOrderItemDto(dto.original_order_item) : undefined,
    };
}

export function mapPurchaseInvoiceDto(dto: PurchaseInvoiceDto): PurchaseInvoice {
    return {
        ...dto,
        contact: dto.contact ? mapContactDto(dto.contact) : undefined,
        supplier: dto.supplier ? mapContactDto(dto.supplier) : undefined,
        items: dto.items.map(mapPurchaseInvoiceItemDto),
        withholdings: dto.withholdings?.map(mapPurchaseInvoiceWithholdingDto),
    };
}

export function mapPurchaseRequestItemDto(dto: PurchaseRequestItemDto): PurchaseRequestItem {
    return { ...dto };
}

export function mapPurchaseRequestDto(dto: PurchaseRequestDto): PurchaseRequest {
    return {
        ...dto,
        provider: dto.provider ? mapContactDto(dto.provider) : undefined,
        items: dto.items?.map(mapPurchaseRequestItemDto),
    };
}
