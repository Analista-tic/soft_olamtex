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

// ============================================
// Notificaciones
// ============================================

export interface NotificationRowDto {
    id: string;
    type: string | null;
    title: string | null;
    body: string | null;
    module: string | null;
    url: string | null;
    entity_id: number | string | null;
    read_at: string | null;
    created_at: string;
    is_read: boolean;
    area?: string | null;
    user_name?: string | null;
}

export interface NotificationPaginatedResponseDto {
    data: NotificationRowDto[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}
