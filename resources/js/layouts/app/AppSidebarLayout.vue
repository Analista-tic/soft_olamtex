<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import NotificationsDropdown from '@/components/NotificationsDropdown.vue';
import UserMenuDropdown from '@/components/UserMenuDropdown.vue';
import type { BreadcrumbItemType } from '@/domain/shared';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="props.breadcrumbs">
                <template #header-right>
                    <div class="flex items-center gap-2 text-sm">
                        <NotificationsDropdown />
                        <span class="h-5 w-px bg-border/80" aria-hidden="true" />
                        <UserMenuDropdown />
                    </div>
                </template>
            </AppSidebarHeader>
            <slot />
        </AppContent>
    </AppShell>
</template>
