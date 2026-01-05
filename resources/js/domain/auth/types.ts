export interface Permission {
    name: string;
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Permission[];
}

export interface User {
    id: number | null;
    name: string;
    email: string;
    avatar?: string | null;
    roles?: Role[];
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface AuthState {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export interface Auth {
    user: User;
}
