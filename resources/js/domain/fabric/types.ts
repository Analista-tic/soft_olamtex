import type { PurchaseInvoiceItem } from '../purchases';

export interface FabricOrderItem {
    id?: number;
    fabric: string;
    baseFabricId?: string | number;
    baseFabric?: { id: number; name: string } | null;
    providerColor: string;
    providerColorCode: string;
    quantity: number;
    estimatedWidth: number;
    price: number;
}

export interface FabricOrder {
    id: number | null;
    fabricProviderId: string;
    fabricProvider?: { id: number; name: string } | null;
    status: string;
    items?: FabricOrderItem[];
}

export interface FabricRoll {
    id: number;
    rollNumber: string;
    purchaseDocumentItemId: number;
    purchaseDocumentItem?: PurchaseInvoiceItem;
    quantity: number;
    rollWidth?: number;
    location?: string;
    description?: string;
    toleranceStatus?: string;
    partialOutput: number;
    actualOutput: number;
    entryDate: string;
    availableQuantity: number;
    isDepleted: boolean;
    createdAt?: string;
    updatedAt?: string;
}

export interface FabricRollView {
    id: number;
    roll: string;
    location: string | null;
    fabric: string;
    color: string;
    colorCode: string;
    width: string;
    rollQty: string;
    partialOutput: string;
    partialAvailability: string;
    exitQuantity: string;
    quantityValue?: number | string;
    exitQuantityValue?: number | string;
    realAvailability: string;
    orderNumber: string;
    provider: string;
    createdAt: string | null;
    creator: string | null;
    status: 'available' | 'depleted';
}
