export interface Tax {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export type TaxPayload = {
    name: string;
    percentage: number;
    status?: boolean;
};

export interface Retention {
    id?: number | null;
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status: 'active' | 'inactive';
    created_at?: string;
    updated_at?: string;
}

export type RetentionPayload = {
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status?: 'active' | 'inactive';
};
