import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';
const STORAGE_KEY = 'appearance';

interface AppearanceAuthUser {
    theme_appearance?: Appearance | null;
}

interface AppearancePageProps {
    [key: string]: unknown;
    auth?: {
        user?: AppearanceAuthUser | null;
    };
}

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem(STORAGE_KEY) as Appearance | null;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const page = usePage<AppearancePageProps>();
    const appearance = ref<Appearance>('system');

    function initializeAppearance() {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.removeItem(STORAGE_KEY);

        const userPref = page.props?.auth?.user?.theme_appearance;

        if (userPref) {
            appearance.value = userPref;
            updateTheme(userPref);

            return;
        }

        appearance.value = 'system';
        updateTheme('system');
    }

    onMounted(() => {
        initializeAppearance();
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        updateTheme(value);

        axios
            .patch(route('settings.appearance.update'), {
                theme_appearance: value,
            })
            .catch(() => {
                // La UI ya quedó aplicada localmente aunque falle la persistencia remota.
            });
    }

    return {
        appearance,
        updateAppearance,
    };
}
