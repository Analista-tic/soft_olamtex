<script setup lang="ts">
import type { BreadcrumbItemType } from '@/domain/shared';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppSidebarLayout :breadcrumbs="props.breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>

