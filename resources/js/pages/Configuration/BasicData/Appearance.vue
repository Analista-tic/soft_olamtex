<script setup lang="ts">
import AccentColorPicker from '@/components/AccentColorPicker.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Configuración de apariencia" description="Actualiza el tema que mejor se adapta a tu entorno de trabajo" />
        <div class="space-y-3">
            <h3 class="text-sm font-medium">Modo</h3>
            <AppearanceTabs />
        </div>
        <div class="h-px bg-border" />
        <AccentColorPicker />
    </div>
</template>
