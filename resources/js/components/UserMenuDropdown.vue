<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import type { SharedData } from '@/domain/shared';
import type { User } from '@/domain/Settings/types';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Settings, User as UserIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const { getInitials } = useInitials();

const user = computed(() => page.props.auth.user as User);
const avatarSrc = computed(() => {
    const rawUrl = (user.value?.avatar_url ?? '').trim();
    if (!rawUrl) {
        return '';
    }

    const version = user.value?.updated_at ? encodeURIComponent(String(user.value.updated_at)) : '';
    const separator = rawUrl.includes('?') ? '&' : '?';
    return version ? `${rawUrl}${separator}v=${version}` : rawUrl;
});

const showAvatar = computed(() => Boolean(avatarSrc.value));

function goProfile() {
    router.visit(route('configuracion.perfil'));
}

function goConfig() {
    router.visit(route('configuracion'));
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex h-9 max-w-[12rem] shrink-0 items-center gap-2 rounded-md border border-transparent bg-transparent px-1.5 text-sm font-medium leading-none text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                aria-label="Menu de usuario"
            >
                <Avatar class="h-6 w-6 shrink-0 overflow-hidden rounded-full">
                    <AvatarImage v-if="showAvatar" :key="user.avatar_url ?? 'no-avatar'" :src="avatarSrc" :alt="user.name" />
                    <AvatarFallback class="rounded-full bg-slate-100 text-sm font-semibold text-slate-500">
                        {{ getInitials(user.name ?? '') }}
                    </AvatarFallback>
                </Avatar>

                <span class="min-w-0 truncate whitespace-nowrap text-left leading-none">
                    {{ user.name }}
                </span>

                <ChevronDown class="h-4 w-4 shrink-0 place-self-center text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            side="bottom"
            :side-offset="10"
            class="z-[60] w-60 rounded-xl"
        >
            <DropdownMenuLabel class="font-normal">
                <p class="truncate text-sm font-medium leading-none">{{ user.name }}</p>
                <p class="mt-1 truncate text-xs leading-none text-muted-foreground">{{ user.email }}</p>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuItem class="cursor-pointer" @select.prevent="goProfile">
                <UserIcon class="mr-2 h-4 w-4" />
                <span>Perfil</span>
            </DropdownMenuItem>

            <DropdownMenuItem class="cursor-pointer" @select.prevent="goConfig">
                <Settings class="mr-2 h-4 w-4" />
                <span>Configuracion</span>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive" @select.prevent="logout">
                <LogOut class="mr-2 h-4 w-4" />
                <span>Cerrar sesion</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
