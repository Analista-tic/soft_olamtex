import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

export const accentColors = ['zinc', 'red', 'rose', 'orange', 'green', 'blue', 'yellow', 'violet'] as const;
export type AccentColor = (typeof accentColors)[number];

interface AccentColorAuthUser {
    theme_color?: AccentColor | null;
}

interface AccentColorPageProps {
    [key: string]: unknown;
    auth?: {
        user?: AccentColorAuthUser | null;
    };
}

const DEFAULT_ACCENT_COLOR: AccentColor = 'blue';
const STORAGE_KEY = 'accentColor';
const COOKIE_KEY = 'accentColor';

const isAccentColor = (value: string | null): value is AccentColor => {
    return value !== null && accentColors.includes(value as AccentColor);
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAccentColor = (): AccentColor => {
    if (typeof window === 'undefined') {
        return DEFAULT_ACCENT_COLOR;
    }

    const storedValue = localStorage.getItem(STORAGE_KEY);
    return isAccentColor(storedValue) ? storedValue : DEFAULT_ACCENT_COLOR;
};

const persistAccentColor = (value: AccentColor) => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(STORAGE_KEY, value);
    setCookie(COOKIE_KEY, value);
};

export const applyAccentColor = (value: AccentColor) => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.accent = value;
};

export const initAccentColor = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const accentColor = getStoredAccentColor();
    persistAccentColor(accentColor);
    applyAccentColor(accentColor);
};

export function useAccentColor() {
    const page = usePage<AccentColorPageProps>();
    const accentColor = ref<AccentColor>(DEFAULT_ACCENT_COLOR);

    const initializeAccentColor = () => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.removeItem(STORAGE_KEY);

        const userPref = page.props?.auth?.user?.theme_color;

        if (userPref && isAccentColor(userPref)) {
            accentColor.value = userPref;
            applyAccentColor(userPref);

            return;
        }

        accentColor.value = DEFAULT_ACCENT_COLOR;
        applyAccentColor(DEFAULT_ACCENT_COLOR);
    };

    onMounted(() => {
        initializeAccentColor();
    });

    const updateAccentColor = (value: AccentColor) => {
        accentColor.value = value;
        applyAccentColor(value);

        axios
            .patch(route('settings.appearance.update'), {
                theme_color: value,
            })
            .catch(() => {
                // La UI ya quedó aplicada localmente aunque falle la persistencia remota.
            });
    };

    return {
        accentColor,
        accentColors,
        updateAccentColor,
    };
}
