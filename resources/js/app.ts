import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia'; // Importar Pinia
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import GlobalLoadingOverlay from './components/common/GlobalLoadingOverlay.vue';
import { useSmartLoading } from './composables/useSmartLoading';

// ImportMeta declarations are provided in resources/js/env.d.ts

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Convierte a mayúsculas el valor real de inputs de texto y textareas al escribir.
// Excluye: email, password, search, number, date y campos marcados con data-no-uppercase.
document.addEventListener('input', (e) => {
    const el = e.target as HTMLElement;
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLTextAreaElement)) return;
    if (el.dataset.noUppercase !== undefined) return;
    if (el instanceof HTMLInputElement) {
        const excluded = ['email', 'password', 'search', 'number', 'date', 'file', 'checkbox', 'radio', 'hidden'];
        if (excluded.includes(el.type)) return;
    }
    const start = (el as HTMLInputElement).selectionStart;
    const end = (el as HTMLInputElement).selectionEnd;
    el.value = el.value.toUpperCase();
    if (start !== null && end !== null && typeof (el as HTMLInputElement).setSelectionRange === 'function') {
        (el as HTMLInputElement).setSelectionRange(start, end);
    }
}, true);

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

const axiosDebugEnabled = import.meta.env.DEV && import.meta.env.VITE_DEBUG_AXIOS === 'true';

// Interceptor para garantizar que FormData no tenga Content-Type forzado
axios.interceptors.request.use((config) => {
    const requestId = (config.headers?.['X-Request-Id'] as string | undefined) ?? undefined;

    if (axiosDebugEnabled) {
        console.debug('[AXIOS][REQ]', {
            method: config.method?.toUpperCase(),
            url: config.url,
            requestId: requestId ?? null,
            timestamp: new Date().toISOString(),
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
        if (axiosDebugEnabled) {
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
        if (axiosDebugEnabled) {
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
        const smartLoading = useSmartLoading();
        smartLoading.bindSmartLoadingToInertia(router as any);

        createApp({
            render: () => h(Fragment, [h(App, props), h(GlobalLoadingOverlay)]),
        })
            .use(plugin)
            .use(pinia) // Usar Pinia
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
