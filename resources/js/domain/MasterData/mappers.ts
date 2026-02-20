import type { CategorySubGroup, ExtraFields, Item } from './types';
import type { ItemDto, CategorySubGroupDto } from './dto';

function parseExtraFields(value?: string | Record<string, string>): ExtraFields | undefined {
    if (!value) return undefined;
    if (typeof value === 'string') {
        try {
            return JSON.parse(value) as ExtraFields;
        } catch {
            return undefined;
        }
    }
    return value;
}

export function mapCategorySubGroupDto(dto: CategorySubGroupDto): CategorySubGroup {
    return {
        id: dto.id,
        name: dto.name,
        category_group_id: dto.category_group_id,
        category_group: dto.category_group
            ? {
                  id: dto.category_group.id,
                  name: dto.category_group.name,
                  created_at: dto.category_group.created_at ?? undefined,
                  updated_at: dto.category_group.updated_at ?? undefined,
              }
            : undefined,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    };
}

export function mapItemDto(dto: ItemDto): Item {
    return {
        id: dto.id,
        warehouse_id: dto.warehouse_id,
        warehouse: dto.warehouse ? (dto.warehouse as any) : undefined,
        category_sub_group_id: dto.category_sub_group_id,
        category_sub_group: dto.category_sub_group ? mapCategorySubGroupDto(dto.category_sub_group) : undefined,
        tax_id: dto.tax_id ?? null,
        name: dto.name,
        code: dto.code,
        reference: dto.reference,
        img: dto.img,
        img_url: dto.img_url ?? undefined,
        stock_min: dto.stock_min,
        stock_max: dto.stock_max,
        bar_code: dto.bar_code ?? undefined,
        negative_movement: Boolean(dto.negative_movement && dto.negative_movement !== 0),
        description: dto.description,
        extra_fields: parseExtraFields(dto.extra_fields ?? undefined),
        status: dto.status,
        item_type: dto.item_type,
        unit_measure: dto.unit_measure,
        created_at: dto.created_at,
        updated_at: dto.updated_at,
    };
}

export function mapItemsDto(dtos: ItemDto[]): Item[] {
    return dtos.map(mapItemDto);
}
