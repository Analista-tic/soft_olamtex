import type { Permission, Role, User } from './types';
import type { PermissionDto, RoleDto, UserDto } from './dto';

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
        email_verified_at: dto.email_verified_at,
        created_at: dto.created_at,
        updated_at: dto.updated_at,
        area_id: dto.area_id ?? null,
        area: dto.area ?? null,
    };
}
