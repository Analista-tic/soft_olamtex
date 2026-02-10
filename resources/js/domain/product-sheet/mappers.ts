import type { ProductSheetDto, ProgrammerDto } from './dto';
import type { ProductSheet, Programmer } from './types';

export function mapProductSheetDto(dto: ProductSheetDto): ProductSheet {
    return {
        id: dto.id,
        type: dto.type,
        reference: dto.reference,
        sample_reference: dto.sample_reference ?? null,
        product_reference: dto.product_reference ?? null,
        display_reference: dto.display_reference ?? null,
        value: dto.value ?? dto.id,
        label: dto.label ?? dto.display_reference ?? dto.reference,
        section_id: dto.section_id,
        group_id: dto.group_id,
        brand_id: dto.brand_id,
        world_id: dto.world_id,
        base_id: dto.base_id,
        billing_price: dto.billing_price,
        selling_price: dto.selling_price,
        sizes: dto.sizes,
        description: dto.description,
        metadata: dto.metadata ?? undefined,
        section: dto.section ?? undefined,
        group: dto.group ?? undefined,
    };
}

export function mapProgrammerDto(dto: ProgrammerDto): Programmer {
    return {
        id: dto.id,
        op_code: dto.op_code ?? null,
        product_sheet_id: dto.product_sheet_id,
        product_sheet: dto.product_sheet ? mapProductSheetDto(dto.product_sheet) : undefined,
        season: dto.season,
        destination: dto.destination,
        destination_id: dto.destination_id,
        priority: dto.priority,
        isEcommerce: dto.is_ecommerce,
        ecommerce_units: dto.ecommerce_units,
        units: dto.units,
        estimated_delivery_date: dto.estimated_delivery_date ?? null,
        status: dto.status,
        is_pending_completion: dto.is_pending_completion ?? false,
        pending_fields: dto.pending_fields ?? [],
        created_at: dto.created_at,
        updated_at: dto.updated_at,
    };
}
