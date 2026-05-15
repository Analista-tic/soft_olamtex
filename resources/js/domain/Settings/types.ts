import type { Auth as SharedAuth, User as SharedUser } from '@/domain/shared/types';

export interface Permission {
    name: string;
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Permission[];
    area_id?: number | null;
}

export type User = SharedUser;

export interface AuthState {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export type Auth = SharedAuth;

// ============================================
// Notificaciones
// ============================================

/**
 * Representa una fila de notificación en la tabla.
 */
export interface NotificationRow {
    /** UUID de la notificación */
    id: string;
    /** Tipo de notificación (ej. 'inventory_request.created') */
    type: string | null;
    /** Título de la notificación */
    title: string | null;
    /** Cuerpo/mensaje de la notificación */
    body: string | null;
    /** Módulo al que pertenece (ej. 'Inventario') */
    module: string | null;
    /** URL de redirección al hacer click */
    url: string | null;
    /** ID de la entidad relacionada */
    entity_id: number | string | null;
    /** Fecha en que se marcó como leída (ISO8601) */
    read_at: string | null;
    /** Fecha en que se archivó (ISO8601) */
    archived_at?: string | null;
    /** Fecha de creación (ISO8601) */
    created_at: string;
    /** Indica si está leída */
    is_read: boolean;
    /** Payload crudo de la notificación */
    data?: NotificationData | null;
    /** Área del usuario (opcional, viene de data.area) */
    area?: string | null;
    /** Nombre del usuario (opcional, viene de data.user_name) */
    user_name?: string | null;
    // Campos específicos de novedades de control de lotes
    op_code?: string | null;
    from_process?: string | null;
    to_process?: string | null;
    reporter?: string | null;
    reported_at?: string | null;
}

/**
 * Datos de notificación que se guardan en el campo 'data'.
 */
export interface NotificationData {
    title: string;
    body: string;
    url?: string;
    type: string;
    module?: string;
    entity_id?: number | string;
    area?: string;
    user_name?: string;
    [key: string]: unknown;
}

/**
 * Filtros disponibles para el listado de notificaciones.
 */
export interface NotificationFilters {
    search?: string | null;
    area?: string | null;
    status?: 'all' | 'unread' | 'read' | null;
    date?: string | null;
}

/**
 * Respuesta paginada del endpoint /data.
 */
export interface NotificationPaginatedResponse {
    data: NotificationRow[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

/**
 * Respuesta del endpoint /latest.
 */
export interface NotificationLatestResponse {
    data: NotificationRow[];
}

/**
 * Respuesta del endpoint /unread-count.
 */
export interface NotificationUnreadCountResponse {
    count: number;
}

/**
 * Objeto de opción para el select de estados.
 */
export interface NotificationStatusOption {
    value: string;
    label: string;
}

/**
 * Objeto de opción para el select de tipos.
 */
export interface NotificationTypeOption {
    value: string;
    label: string;
}
