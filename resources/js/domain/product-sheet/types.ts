export interface OptionParams {
    id: number;
    name: string;
    createdAt: string;
    updatedAt: string;
}

export interface ProductSheet {
    id: number;
    reference: string;
    sectionId: number;
    groupId: number;
    brandId: number;
    worldId: number;
    baseId: number;
    billingPrice?: number;
    sellingPrice?: number;
    sizes?: string;
    description?: string;
    metadata?: Record<string, unknown>;
}

export interface Programmer {
    id: number;
    productSheetId: number | null;
    productSheet?: ProductSheet | null;
    season: string | null;
    destination?: { id: number; name: string } | null;
    destinationId: number | null;
    priority: string | null;
    isEcommerce: boolean;
    ecommerceUnits: Record<string, number> | string | null;
    units: number | null;
    status: 'complete' | 'incomplete';
    createdAt: string;
    updatedAt: string;
}
