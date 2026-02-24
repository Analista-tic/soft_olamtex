export interface TaxDto {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface RetentionDto {
    id?: number | null;
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status: 'active' | 'inactive';
    created_at?: string | null;
    updated_at?: string | null;
}
