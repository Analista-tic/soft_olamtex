import type { SharedData } from '@/domain/shared/types';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        roles: [] as string[],
        permissions: [] as string[],
        _fetchPromise: null as Promise<void> | null,
    }),
    actions: {
        async fetchUser(force = false) {
            if (!force && this.user) return;
            if (this._fetchPromise) return this._fetchPromise;

            // HandleInertiaRequests::share() ya manda auth.user/roles/permissions en
            // cada navegación — si ya están en la página actual, usarlos evita un
            // round-trip HTTP extra a GET /user que repetiría exactamente lo mismo.
            const sharedAuth = usePage<SharedData>().props.auth;
            if (!force && sharedAuth?.user) {
                this.user = sharedAuth.user;
                this.roles = sharedAuth.roles ?? [];
                this.permissions = sharedAuth.permissions ?? [];
                return;
            }

            this._fetchPromise = axios
                .get('/user')
                .then(({ data }) => {
                    this.user = data.user;
                    this.roles = data.roles;
                    this.permissions = data.permissions;
                })
                .catch((error) => {
                    console.error('Error al obtener los datos del usuario:', error);
                    throw error;
                })
                .finally(() => {
                    this._fetchPromise = null;
                });
            return this._fetchPromise;
        },
        hasRole(role: string): boolean {
            return this.roles.includes(role);
        },
        hasAnyRole(roles: string[]): boolean {
            return roles.some((role) => this.roles.includes(role));
        },
        can(permission: string): boolean {
            return this.permissions.includes(permission);
        },
        canAny(permissions: string[]): boolean {
            return permissions.some((permission) => this.permissions.includes(permission));
        },
        canAll(permissions: string[]): boolean {
            return permissions.every((permission) => this.permissions.includes(permission));
        },
    },
});
