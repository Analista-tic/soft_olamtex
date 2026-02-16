<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type BadgeStatus = 'sin_datos' | 'pendiente' | 'calculado';

const props = withDefaults(defineProps<{
    label: string;
    value: string;
    badgeStatus?: BadgeStatus;
    badgeText?: string | null;
    badgeClass?: string;
    containerClass?: string;
    valueClass?: string;
    tooltipText?: string | null;
}>(), {
    badgeStatus: 'calculado',
    badgeText: null,
    badgeClass: '',
    containerClass: '',
    valueClass: '',
    tooltipText: null,
});

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
    <div
        :class="cn(
            'relative h-full rounded-lg bg-background p-4 ring-1 ring-border/60',
            containerClass,
        )"
    >
        <Badge
            variant="outline"
            :class="cn(
                'absolute right-3 top-3',
                defaultBadgeClass,
                badgeClass,
            )"
        >
            {{ badgeText ?? defaultBadgeText }}
        </Badge>

        <div class="flex h-full min-h-[124px] flex-col justify-between gap-4 pr-20">
            <div class="flex items-center gap-1.5">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                <TooltipProvider v-if="tooltipText" :delay-duration="100">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-5 w-5 rounded-full p-0 text-muted-foreground hover:text-foreground"
                            >
                                ?
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top" class="max-w-xs text-xs">
                            {{ tooltipText }}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <p :class="cn('text-2xl font-semibold leading-none tabular-nums text-foreground', valueClass)">
                {{ value }}
            </p>
        </div>
    </div>
</template>
