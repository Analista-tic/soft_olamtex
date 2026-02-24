/**
 * Auth domain - re-exports from Settings for backward compatibility.
 * Prefer importing from @/domain/Settings when possible.
 */
export type { Auth, AuthState, Permission, Role, User } from './types';
export type { PermissionDto, RoleDto, UserDto } from './dto';
export { mapPermissionDto, mapRoleDto, mapUserDto } from './mappers';
