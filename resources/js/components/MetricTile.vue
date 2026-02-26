<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

type BadgeStatus = 'sin_datos' | 'pendiente' | 'calculado';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        dense?: boolean;
        showBadge?: boolean;
        badgeStatus?: BadgeStatus;
        badgeText?: string | null;
        badgeClass?: string;
        containerClass?: string;
        valueClass?: string;
        tooltipText?: string | null;
    }>(),
    {
        badgeStatus: 'calculado',
        dense: false,
        showBadge: true,
        badgeText: null,
        badgeClass: '',
        containerClass: '',
        valueClass: '',
        tooltipText: null,
    },
);

const defaultBadgeText = computed(() => {
    if (props.badgeStatus === 'sin_datos') return 'Sin datos';
    if (props.badgeStatus === 'pendiente') return 'Pendiente';
    return 'Calculado';
});

const defaultBadgeClass = computed(() => {
    if (props.badgeStatus === 'sin_datos') return 'border-slate-200 bg-slate-50 text-slate-600';
    if (props.badgeStatus === 'pendiente') return 'border-amber-200 bg-amber-50 text-amber-700';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
});
</script>

<template>
    <div :class="cn('relative h-full rounded-lg bg-background ring-1 ring-border/60', 'p-4', containerClass)">
        <template v-if="dense">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-1.5">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                    <HelpTooltip v-if="tooltipText" :text="tooltipText" :aria-label="`Ayuda sobre ${label}`" />
                </div>
                <Badge v-if="showBadge" variant="outline" :class="cn('shrink-0', defaultBadgeClass, badgeClass)">
                    {{ badgeText ?? defaultBadgeText }}
                </Badge>
            </div>

            <div class="mt-2 flex flex-col gap-2">
                <p :class="cn('text-2xl font-semibold tabular-nums leading-none text-foreground', valueClass)">
                    {{ value }}
                </p>
            </div>
        </template>

        <template v-else>
            <Badge v-if="showBadge" variant="outline" :class="cn('absolute right-3 top-3', defaultBadgeClass, badgeClass)">
                {{ badgeText ?? defaultBadgeText }}
            </Badge>

            <div :class="cn('flex h-full min-h-[124px] flex-col justify-between gap-4', showBadge ? 'pr-20' : '')">
                <div class="flex items-center gap-1.5">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                    <HelpTooltip v-if="tooltipText" :text="tooltipText" :aria-label="`Ayuda sobre ${label}`" />
                </div>

                <p :class="cn('text-2xl font-semibold tabular-nums leading-none text-foreground', valueClass)">
                    {{ value }}
                </p>
            </div>
        </template>
    </div>
</template>
