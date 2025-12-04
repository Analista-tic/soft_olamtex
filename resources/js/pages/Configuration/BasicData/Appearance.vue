<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall
            title="Appearance settings"
            description="Actualiza el tema que mejor se adapta a tu entorno de trabajo"
        />
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <AppearanceTabs />
        </div>
    </div>
</template>
