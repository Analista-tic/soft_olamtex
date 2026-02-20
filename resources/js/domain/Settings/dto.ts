export interface PermissionDto {
    name: string;
}

export interface RoleDto {
    id: number | null;
    name: string;
    permissions: PermissionDto[];
    area_id?: number | null;
}

export interface UserDto {
    id: number | null;
    name: string;
    email: string;
    avatar?: string;
    roles?: RoleDto[];
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    area_id?: number | null;
    area?: { id: number; nombre: string } | null;
}
