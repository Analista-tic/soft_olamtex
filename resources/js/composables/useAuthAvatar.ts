import type { SharedData, User } from '@/domain/shared/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const sharedAvatarUrl = ref<string | null>(null);
let initialized = false;

function stripQuery(url: string): string {
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

function normalizeVersion(version: unknown): string | null {
    if (version === null || version === undefined) {
        return null;
    }

    const raw = String(version).trim();
    if (!raw) {
        return null;
    }

    const timestamp = Date.parse(raw);
    return Number.isNaN(timestamp) ? raw : String(timestamp);
}

function withCacheBust(url: string | null, version?: unknown): string | null {
    if (!url) {
        return null;
    }

    const [base, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    const normalizedVersion = normalizeVersion(version);
    params.set('v', normalizedVersion ?? String(Date.now()));

    return `${base}?${params.toString()}`;
}

export function useAuthAvatar() {
    const page = usePage<SharedData>();

    const pageUser = computed(() => page.props.auth?.user as User | null | undefined);

    const pageAvatarUrl = computed(() => {
        return normalizeUrl(pageUser.value?.avatar_url ?? null);
    });

    const pageAvatarVersion = computed(() => normalizeVersion(pageUser.value?.updated_at ?? null));

    if (!initialized) {
        sharedAvatarUrl.value = withCacheBust(pageAvatarUrl.value, pageAvatarVersion.value);
        initialized = true;
    }

    watch(
        [pageAvatarUrl, pageAvatarVersion],
        ([nextUrl, nextVersion]) => {
            const currentBase = sharedAvatarUrl.value ? stripQuery(sharedAvatarUrl.value) : null;
            const nextBase = nextUrl ? stripQuery(nextUrl) : null;
            const expectedUrl = withCacheBust(nextUrl, nextVersion);

            if (currentBase !== nextBase || sharedAvatarUrl.value !== expectedUrl) {
                sharedAvatarUrl.value = expectedUrl;
            }
        },
        { immediate: true },
    );

    const avatarUrl = computed(() => sharedAvatarUrl.value);
    const hasAvatar = computed(() => Boolean(sharedAvatarUrl.value));

    function setAvatarUrl(nextUrl: string | null, options?: { cacheBust?: boolean; version?: string | number | null }) {
        const normalized = normalizeUrl(nextUrl);
        sharedAvatarUrl.value = options?.cacheBust === false ? normalized : withCacheBust(normalized, options?.version);
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
