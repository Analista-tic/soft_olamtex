import '../css/app.css';

import axios from 'axios';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia'; // Importar Pinia
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

// ImportMeta declarations are provided in resources/js/env.d.ts

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Configuracion centralizada de Axios para Laravel + Inertia
// SOLO usar XSRF-TOKEN cookie (Laravel lo genera automaticamente)
// NO fijar X-CSRF-TOKEN global - Inertia lo maneja por su cuenta
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
axios.defaults.withCredentials = true; // Siempre enviar cookies (requerido para CSRF)

// Eliminar Content-Type de headers defaults para que axios detecte FormData automaticamente
// Inicializar objetos de headers si no existen, luego eliminar Content-Type
axios.defaults.headers.post = axios.defaults.headers.post || {};
axios.defaults.headers.put = axios.defaults.headers.put || {};
axios.defaults.headers.patch = axios.defaults.headers.patch || {};
delete axios.defaults.headers.post['Content-Type'];
delete axios.defaults.headers.put['Content-Type'];
delete axios.defaults.headers.patch['Content-Type'];
delete axios.defaults.headers.common['Content-Type'];

// Interceptor para garantizar que FormData no tenga Content-Type forzado
axios.interceptors.request.use((config) => {
    const requestId = (config.headers?.['X-Request-Id'] as string | undefined) ?? undefined;

    if (import.meta.env.DEV) {
        const stack = new Error().stack
            ?.split('\n')
            .slice(1, 4)
            .map((line) => line.trim())
            .join(' | ');

        console.debug('[AXIOS][REQ]', {
            method: config.method?.toUpperCase(),
            url: config.url,
            requestId: requestId ?? null,
            timestamp: new Date().toISOString(),
            stack: stack ?? null,
        });
    }

    if (config.data instanceof FormData) {
        // Eliminar Content-Type para que el browser lo detecte automaticamente con boundary
        delete config.headers['Content-Type'];
    }
    return config;
});

// NO fijar X-CSRF-TOKEN desde meta tag: Inertia y el cookie XSRF-TOKEN ya lo manejan.
// Configurar esto globalmente puede causar conflictos cuando el meta tag queda desactualizado.

axios.interceptors.response.use(
    (response) => {
        if (import.meta.env.DEV) {
            const requestId = (response.config.headers?.['X-Request-Id'] as string | undefined) ?? undefined;
            console.debug('[AXIOS][RES]', {
                method: response.config.method?.toUpperCase(),
                url: response.config.url,
                status: response.status,
                requestId: requestId ?? null,
                timestamp: new Date().toISOString(),
            });
        }

        return response;
    },
    (error) => {
        if (import.meta.env.DEV) {
            const requestId = (error?.config?.headers?.['X-Request-Id'] as string | undefined) ?? undefined;
            console.debug('[AXIOS][ERR]', {
                method: error?.config?.method?.toUpperCase(),
                url: error?.config?.url,
                status: error?.response?.status ?? null,
                requestId: requestId ?? null,
                timestamp: new Date().toISOString(),
            });
        }

        if (error?.response?.status === 401) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    },
);

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia(); // Crear instancia de Pinia

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia) // Usar Pinia
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
localStorage.setItem('appearance', localStorage.getItem('appearance') || 'light'); // Forzar 'light' si no hay preferencia guardada

