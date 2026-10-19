<script setup lang="ts">
import { TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { Component, HTMLAttributes } from 'vue';

export interface PillTab {
    value: string;
    label: string;
    icon?: Component;
    /** Contador junto a la etiqueta; se oculta si es 0 o no se define. */
    count?: number | null;
    disabled?: boolean;
    /** Permite ocultar una pestaña sin filtrar el arreglo. */
    hidden?: boolean;
}

const props = withDefaults(
    defineProps<{
        tabs: readonly PillTab[];
        /** `md` para pantallas/secciones; `sm` para diálogos y espacios reducidos. */
        size?: 'sm' | 'md';
        /** `center` solo en pantallas donde las pestañas son la navegación principal (Ficha técnica). */
        align?: 'start' | 'center';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md', align: 'start' },
);

const sizeClass = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3 py-2 text-sm',
} as const;

const gapClass = { sm: 'gap-1.5', md: 'gap-2' } as const;
const iconClass = { sm: 'h-3.5 w-3.5', md: 'h-4 w-4' } as const;
</script>

<template>
    <!-- Debe ir dentro de un <Tabs v-model="...">. Ver docs/PATRON_TABS.md -->
    <div :class="cn('mb-3 flex max-w-full shrink-0 overflow-x-auto scrollbar-none', align === 'center' ? 'justify-center' : 'justify-start', props.class)">
        <TabsList class="inline-flex w-fit flex-nowrap items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
            <template v-for="tab in tabs" :key="tab.value">
                <TabsTrigger
                    v-if="!tab.hidden"
                    :value="tab.value"
                    :disabled="tab.disabled"
                    :class="
                        cn(
                            'inline-flex shrink-0 items-center whitespace-nowrap rounded-full font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground data-[state=active]:border data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm',
                            sizeClass[size],
                        )
                    "
                >
                    <span :class="cn('inline-flex shrink-0 items-center whitespace-nowrap', gapClass[size])">
                        <component :is="tab.icon" v-if="tab.icon" :class="cn('shrink-0', iconClass[size])" />
                        <span class="shrink-0">{{ tab.label }}</span>
                        <span
                            v-if="tab.count != null && tab.count > 0"
                            class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold leading-none text-primary-foreground"
                        >
                            {{ tab.count }}
                        </span>
                    </span>
                </TabsTrigger>
            </template>
        </TabsList>
    </div>
</template>
