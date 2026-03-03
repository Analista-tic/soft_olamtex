import type { SharedData, User } from '@/domain/shared/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const sharedAvatarUrl = ref<string | null>(null);
let initialized = false;

function stripVersion(url: string): string {
    const [base] = url.split('?');
    return base ?? url;
}

function normalizeUrl(url: string | null | undefined): string | null {
    if (!url) {
        return null;
    }

    const trimmed = url.trim();
    return trimmed.length > 0 ? trimmed : null;
}

function withCacheBust(url: string | null): string | null {
    if (!url) {
        return null;
    }

    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}v=${Date.now()}`;
}

export function useAuthAvatar() {
    const page = usePage<SharedData>();

    const pageAvatarUrl = computed(() => {
        const user = page.props.auth?.user as User | null | undefined;
        return normalizeUrl(user?.avatar_url ?? null);
    });

    if (!initialized) {
        sharedAvatarUrl.value = pageAvatarUrl.value;
        initialized = true;
    }

    watch(
        pageAvatarUrl,
        (next) => {
            const currentBase = sharedAvatarUrl.value ? stripVersion(sharedAvatarUrl.value) : null;
            const nextBase = next ? stripVersion(next) : null;

            if (currentBase !== nextBase) {
                sharedAvatarUrl.value = next;
            }
        },
        { immediate: true },
    );

    const avatarUrl = computed(() => sharedAvatarUrl.value);
    const hasAvatar = computed(() => Boolean(sharedAvatarUrl.value));

    function setAvatarUrl(nextUrl: string | null, options?: { cacheBust?: boolean }) {
        const normalized = normalizeUrl(nextUrl);
        sharedAvatarUrl.value = options?.cacheBust === false ? normalized : withCacheBust(normalized);
    }

    function clearAvatarUrl() {
        sharedAvatarUrl.value = null;
    }

    return {
        avatarUrl,
        hasAvatar,
        setAvatarUrl,
        clearAvatarUrl,
    };
}

