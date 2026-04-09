import type { AdditionalContactDto, CategorySubGroupDto, ContactDto, ItemDto } from './dto';
import type {
    AdditionalContact,
    AdditionalField,
    AdditionalFieldDialogFormState,
    CategorySubGroup,
    Contact,
    ExtraFields,
    Item,
    Parameter,
    ParameterDialogFormState,
} from './types';

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

export function normalizeBooleanFlag(value: unknown, fallback = false): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (normalized === '') {
            return fallback;
        }

        if (['1', 'true', 'on', 'yes'].includes(normalized)) {
            return true;
        }

        if (['0', 'false', 'off', 'no'].includes(normalized)) {
            return false;
        }

        const numericValue = Number(normalized);
        if (!Number.isNaN(numericValue)) {
            return numericValue !== 0;
        }
    }

    return fallback;
}

export function mapAdditionalFieldToDialogForm(field?: Partial<AdditionalField> | null): AdditionalFieldDialogFormState {
    return {
        name: field?.name ?? '',
        field_type: field?.field_type ?? 'text',
        options: Array.isArray(field?.options) ? field.options : [],
        default_value: String(field?.default_value ?? ''),
        description: field?.description ?? '',
        is_required: normalizeBooleanFlag(field?.is_required, false),
        status: normalizeBooleanFlag(field?.status, true),
        id: field?.id,
        position: field?.position,
        created_at: field?.created_at,
        updated_at: field?.updated_at,
    };
}

export function mapAdditionalFieldRecord(field?: (Partial<AdditionalField> & { label?: string }) | null): AdditionalField {
    return {
        id: field?.id,
        name: field?.name ?? field?.label ?? '',
        field_type: field?.field_type ?? 'text',
        options: Array.isArray(field?.options) ? field.options : [],
        default_value: field?.default_value ?? '',
        description: field?.description ?? '',
        is_required: normalizeBooleanFlag(field?.is_required, false),
        status: normalizeBooleanFlag(field?.status, true),
        position: field?.position,
        created_at: field?.created_at,
        updated_at: field?.updated_at,
    };
}

export function mapParameterToDialogForm(parameter?: Partial<Parameter> | Partial<ParameterDialogFormState> | null): ParameterDialogFormState {
    return {
        id: parameter?.id,
        key: parameter?.key,
        label: parameter?.label ?? '',
        description: parameter?.description ?? '',
        value: parameter?.value ?? [],
        status: normalizeBooleanFlag(parameter?.status, true),
    };
}

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
        origin_type: dto.origin_type,
        was_edited_after_import: Boolean(dto.was_edited_after_import),
        imported_at: dto.imported_at ?? null,
        import_batch_id: dto.import_batch_id ?? null,
        item_type: dto.item_type,
        tracks_inventory: dto.tracks_inventory ?? dto.item_type !== 'service',
        unit_measure: dto.unit_measure,
        income_account_id: dto.income_account_id ?? null,
        inventory_account_id: dto.inventory_account_id ?? null,
        cost_of_sale_account_id: dto.cost_of_sale_account_id ?? null,
        created_at: dto.created_at,
        updated_at: dto.updated_at,
    };
}

export function mapItemsDto(dtos: ItemDto[]): Item[] {
    return dtos.map(mapItemDto);
}
