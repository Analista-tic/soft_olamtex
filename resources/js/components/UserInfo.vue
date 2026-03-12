<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/domain/Settings';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

const avatarSrc = computed(() => {
    const rawUrl = (props.user.avatar_url ?? '').trim();
    if (!rawUrl) {
        return '';
    }

    const [base, query = ''] = rawUrl.split('?');
    const params = new URLSearchParams(query);
    const versionRaw = String(props.user.updated_at ?? '').trim();

    if (versionRaw) {
        const parsedVersion = Date.parse(versionRaw);
        params.set('v', Number.isNaN(parsedVersion) ? versionRaw : String(parsedVersion));
    } else if (!params.has('v')) {
        params.set('v', '1');
    }

    return `${base}?${params.toString()}`;
});
const showAvatar = computed(() => avatarSrc.value !== '');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="avatarSrc" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-foreground dark:text-foreground">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
