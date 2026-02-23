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

export type RetentionStatus = 'active' | 'inactive';

export interface Retention {
    id?: number | null;
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status: RetentionStatus;
    created_at?: string;
    updated_at?: string;
}

export type RetentionForm = Omit<Retention, 'description'> & { description: string };

export type RetentionPayload = {
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status?: RetentionStatus;
};

export type RetentionListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    type?: string;
    status?: RetentionStatus;
};
