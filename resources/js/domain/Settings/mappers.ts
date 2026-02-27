import type { NotificationRowDto, PermissionDto, RoleDto, UserDto } from './dto';
import type { NotificationRow, Permission, Role, User } from './types';

export function mapPermissionDto(dto: PermissionDto): Permission {
    return {
        name: dto.name,
    };
}

export function mapRoleDto(dto: RoleDto): Role {
    return {
        id: dto.id,
        name: dto.name,
        permissions: dto.permissions.map(mapPermissionDto),
    };
}

export function mapUserDto(dto: UserDto): User {
    return {
        id: dto.id,
        name: dto.name,
        email: dto.email,
        avatar: dto.avatar,
        roles: dto.roles ? dto.roles.map(mapRoleDto) : undefined,
        email_verified_at: dto.email_verified_at ?? null,
        created_at: dto.created_at ?? '',
        updated_at: dto.updated_at ?? '',
        area_id: dto.area_id ?? null,
        area: dto.area ?? null,
    };
}

// ============================================
// Notificaciones
// ============================================

export function mapNotificationRowDto(dto: NotificationRowDto): NotificationRow {
    return {
        id: dto.id,
        type: dto.type,
        title: dto.title,
        body: dto.body,
        module: dto.module,
        url: dto.url,
        entity_id: dto.entity_id,
        read_at: dto.read_at,
        created_at: dto.created_at,
        is_read: dto.is_read,
        area: dto.area ?? null,
        user_name: dto.user_name ?? null,
    };
}

export function mapNotificationRowDtoList(dtos: NotificationRowDto[]): NotificationRow[] {
    return dtos.map(mapNotificationRowDto);
}
