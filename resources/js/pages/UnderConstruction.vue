<script setup lang="ts">
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    title?: string | null;
}>();

const moduleTitle = computed(() => (props.title && props.title.trim().length ? props.title : null));
const pageTitle = computed(() => (moduleTitle.value ? `${moduleTitle.value} - En desarrollo` : 'En desarrollo'));

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base = { title: 'En desarrollo', href: route('under-construction') };
    if (!moduleTitle.value) {
        return [base];
    }

    return [
        base,
        {
            title: moduleTitle.value,
            href: route('under-construction', { modulo: toSlug(moduleTitle.value), titulo: moduleTitle.value }),
        },
    ];
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 items-center justify-center p-6">
            <div class="max-w-lg space-y-3 rounded-xl border border-sidebar-border/70 p-6 text-center dark:border-sidebar-border">
                <h1 class="text-2xl font-semibold">
                    <span v-if="moduleTitle">{{ moduleTitle }}</span>
                    <span v-else>Módulo en desarrollo</span>
                </h1>
                <p class="text-sm text-muted-foreground">Este módulo aún no está disponible. Estamos trabajando para habilitarlo lo antes posible.</p>
            </div>
        </div>
    </AppLayout>
</template>
