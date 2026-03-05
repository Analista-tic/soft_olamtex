<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AccentColorPicker from '@/components/AccentColorPicker.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import type { BreadcrumbItem } from '@/domain/shared';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configuración de apariencia',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configuración de apariencia" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Configuración de apariencia" description="Update your account's appearance settings" />
                <div class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div class="space-y-3">
                        <h3 class="text-sm font-medium">Modo</h3>
                        <AppearanceTabs />
                    </div>
                    <div class="h-px bg-border" />
                    <AccentColorPicker />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
