import type { FabricOrder, FabricOrderItem, FabricRoll, FabricRollView } from './types';
import type { FabricOrderDto, FabricOrderItemDto, FabricRollDto, FabricRollViewDto } from './dto';

export function mapFabricOrderItemDto(dto: FabricOrderItemDto): FabricOrderItem {
    return {
        id: dto.id,
        fabric: dto.fabric,
        baseFabricId: dto.base_fabric_id,
        baseFabric: dto.base_fabric
            ? { id: dto.base_fabric.id, name: dto.base_fabric.name }
            : undefined,
        providerColor: dto.provider_color,
        providerColorCode: dto.provider_color_code,
        quantity: dto.quantity,
        estimatedWidth: dto.estimated_width,
        price: dto.price,
    };
}

export function mapFabricOrderDto(dto: FabricOrderDto): FabricOrder {
    return {
        id: dto.id,
        fabricProviderId: dto.fabric_provider_id,
        fabricProvider: dto.fabric_provider,
        status: dto.status,
        items: dto.items?.map(mapFabricOrderItemDto),
    };
}

export function mapFabricRollDto(dto: FabricRollDto): FabricRoll {
    return {
        id: dto.id,
        rollNumber: dto.roll_number,
        purchaseDocumentItemId: dto.purchase_document_item_id,
        purchaseDocumentItem: dto.purchase_document_item as FabricRoll['purchaseDocumentItem'],
        quantity: dto.quantity,
        rollWidth: dto.roll_width,
        location: dto.location,
        description: dto.description,
        toleranceStatus: dto.tolerance_status,
        partialOutput: dto.partial_output,
        actualOutput: dto.actual_output,
        entryDate: dto.entry_date,
        availableQuantity: dto.available_quantity,
        isDepleted: dto.is_depleted,
        createdAt: dto.created_at ?? undefined,
        updatedAt: dto.updated_at ?? undefined,
    };
}

export function mapFabricRollViewDto(dto: FabricRollViewDto): FabricRollView {
    return {
        id: dto.id,
        roll: dto.roll,
        location: dto.location,
        fabric: dto.fabric,
        color: dto.color,
        colorCode: dto.color_code,
        width: dto.width,
        rollQty: dto.roll_qty,
        partialOutput: dto.partial_output,
        partialAvailability: dto.partial_availability,
        exitQuantity: dto.exit_quantity,
        quantityValue: dto.quantity_value,
        exitQuantityValue: dto.exit_quantity_value,
        realAvailability: dto.real_availability,
        orderNumber: dto.order_number,
        provider: dto.provider,
        createdAt: dto.created_at,
        creator: dto.creator,
        status: dto.status,
    };
}
