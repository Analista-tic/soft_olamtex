import axios from 'axios';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        roles: [] as string[],
        permissions: [] as string[],
    }),
    actions: {
        async fetchUser() {
            const { data } = await axios.get('/user');
            
            this.user = data.user;
            this.roles = data.roles;
            this.permissions = data.permissions;
        },
        hasRole(role: string): boolean {
            return this.roles.includes(role);
        },
        can(permission: string): boolean {
            return this.permissions.includes(permission);
        },
    },
});
