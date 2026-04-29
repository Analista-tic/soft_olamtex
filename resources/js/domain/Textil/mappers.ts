import type { FabricOrderDto, FabricOrderItemDto, FabricRollDto, FabricRollViewDto } from './dto';
import type { FabricOrder, FabricOrderItem, FabricRoll, FabricRollView } from './types';

export function mapFabricOrderItemDto(dto: FabricOrderItemDto): FabricOrderItem {
    return {
        id: dto.id,
        fabric: dto.fabric,
        base_fabric_id: dto.base_fabric_id,
        base_fabric: dto.base_fabric ? { id: dto.base_fabric.id, name: dto.base_fabric.name } : undefined,
        provider_color: dto.provider_color,
        provider_color_code: dto.provider_color_code,
        quantity: dto.quantity,
        estimated_width: dto.estimated_width,
        price: dto.price,
    };
}

export function mapFabricOrderDto(dto: FabricOrderDto): FabricOrder {
    return {
        id: dto.id,
        fabric_provider_id: dto.fabric_provider_id,
        fabric_provider: dto.fabric_provider,
        status: dto.status,
        items: dto.items?.map(mapFabricOrderItemDto),
    };
}

export function mapFabricRollDto(dto: FabricRollDto): FabricRoll {
    return {
        id: dto.id,
        roll_number: dto.roll_number,
        purchase_document_item_id: dto.purchase_document_item_id,
        purchase_document_item: dto.purchase_document_item as FabricRoll['purchase_document_item'],
        quantity: dto.quantity,
        roll_width: dto.roll_width,
        location: dto.location,
        description: dto.description,
        tolerance_status: dto.tolerance_status,
        partial_output: dto.partial_output,
        actual_output: dto.actual_output,
        entry_date: dto.entry_date,
        available_quantity: dto.available_quantity,
        is_depleted: dto.is_depleted,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    };
}

export function mapFabricRollViewDto(dto: FabricRollViewDto): FabricRollView {
    return { ...dto };
}
