<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import NotificationsDropdown from '@/components/NotificationsDropdown.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
                    <NotificationsDropdown />
                    <UserMenuDropdown />
                </template>
            </AppSidebarHeader>
            <slot />
        </AppContent>
    </AppShell>
</template>
