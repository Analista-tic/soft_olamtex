import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { route as ziggyRoute } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')) as unknown as any,
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });

            // Configure Ziggy for SSR...
            const ziggy = page.props.ziggy as unknown as Record<string, any>;
            const ziggyConfig = {
                ...(ziggy || {}),
                location: new URL(ziggy.location),
            };

            // Create route function...
            const route = (name: string, params?: any, absolute?: boolean) =>
                ziggyRoute(name, params, absolute, ziggyConfig as any);

            // Make route function available globally (cast to any to satisfy typings)
            app.config.globalProperties.route = route as any;

            // Make route function available globally for SSR (attach to globalThis safely)
            if (typeof window === 'undefined') {
                (globalThis as any).route = route;
            }

            app.use(plugin);

            return app;
        },
    }),
);
