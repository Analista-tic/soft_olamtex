import { onMounted, ref } from 'vue';

export const accentColors = ['zinc', 'red', 'rose', 'orange', 'green', 'blue', 'yellow', 'violet'] as const;
export type AccentColor = (typeof accentColors)[number];

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
    const accentColor = ref<AccentColor>(DEFAULT_ACCENT_COLOR);

    onMounted(() => {
        initAccentColor();
        accentColor.value = getStoredAccentColor();
    });

    const updateAccentColor = (value: AccentColor) => {
        accentColor.value = value;
        persistAccentColor(value);
        applyAccentColor(value);
    };

    return {
        accentColor,
        accentColors,
        updateAccentColor,
    };
}
