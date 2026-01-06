import type { ProductSheet, Programmer } from './types';
import type { ProductSheetDto, ProgrammerDto } from './dto';

export function mapProductSheetDto(dto: ProductSheetDto): ProductSheet {
    return {
        id: dto.id,
        reference: dto.reference,
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
    };
}

export function mapProgrammerDto(dto: ProgrammerDto): Programmer {
    return {
        id: dto.id,
        product_sheet_id: dto.product_sheet_id,
        product_sheet: dto.product_sheet ? mapProductSheetDto(dto.product_sheet) : undefined,
        season: dto.season,
        destination: dto.destination,
        destination_id: dto.destination_id,
        priority: dto.priority,
        is_ecommerce: dto.is_ecommerce,
        ecommerce_units: dto.ecommerce_units,
        units: dto.units,
        status: dto.status,
        created_at: dto.created_at,
        updated_at: dto.updated_at,
    };
}
