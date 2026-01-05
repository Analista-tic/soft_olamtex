import type { ProductSheet, Programmer } from './types';
import type { ProductSheetDto, ProgrammerDto } from './dto';

export function mapProductSheetDto(dto: ProductSheetDto): ProductSheet {
    return {
        id: dto.id,
        reference: dto.reference,
        sectionId: dto.section_id,
        groupId: dto.group_id,
        brandId: dto.brand_id,
        worldId: dto.world_id,
        baseId: dto.base_id,
        billingPrice: dto.billing_price,
        sellingPrice: dto.selling_price,
        sizes: dto.sizes,
        description: dto.description,
        metadata: dto.metadata ?? undefined,
    };
}

export function mapProgrammerDto(dto: ProgrammerDto): Programmer {
    return {
        id: dto.id,
        productSheetId: dto.product_sheet_id,
        productSheet: dto.product_sheet ? mapProductSheetDto(dto.product_sheet) : undefined,
        season: dto.season,
        destination: dto.destination,
        destinationId: dto.destination_id,
        priority: dto.priority,
        isEcommerce: dto.isEcommerce,
        ecommerceUnits: dto.ecommerce_units,
        units: dto.units,
        status: dto.status,
        createdAt: dto.created_at,
        updatedAt: dto.updated_at,
    };
}
