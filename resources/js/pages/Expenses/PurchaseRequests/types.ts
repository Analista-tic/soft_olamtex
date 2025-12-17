export type PurchaseRequestItem = {
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    __name?: string;
};

export type PurchaseRequestForm = {
    id?: number;
    request_number: string;
    contact_id: number | string | null;
    requester_user_id: number | null;
    date: string;
    estimated_amount: number | null;
    description: string;
    items: PurchaseRequestItem[];
    status?: string | null;
};
