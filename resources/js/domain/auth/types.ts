import type { Auth as SharedAuth, User as SharedUser } from '@/domain/shared';

export interface Permission {
    name: string;
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Permission[];
}

export type User = SharedUser;

export interface AuthState {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export type Auth = SharedAuth;
