<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { NavItem } from '@/domain/shared';
import type { User } from '@/domain/auth';
import { Link } from '@inertiajs/vue3';
import { LogOut, Pencil } from 'lucide-vue-next';

interface Props {
    user: User;
    items: NavItem[];
}

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
            <Link
                class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                :href="route('configuracion.empresa')"
                aria-label="Ir a configuración de empresa"
            >
                <Pencil class="h-4 w-4" />
            </Link>
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <template v-for="item in items" :key="item.title">
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-sidebar-foreground/90 transition-colors hover:bg-muted hover:text-sidebar-foreground"
                :href="item.href"
            >
                <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                <span>{{ item.title }}</span>
            </Link>
        </DropdownMenuItem>
    </template>
    <DropdownMenuSeparator v-if="items.length" />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
