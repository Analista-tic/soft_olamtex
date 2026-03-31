<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Activity, ClipboardList, Eye, Flag, MessageSquareWarning, MoreVertical, PackageCheck, Scissors, Send } from 'lucide-vue-next';
import type { BatchControlRow } from './types';

const props = withDefaults(
    defineProps<{
        row: BatchControlRow;
        /** Proceso activo del tab en el que se renderiza esta fila */
        activeProcess?: string;
        /** Si la fila está activa (current_process === activeProcess). Cuando false → acciones de proceso son solo lectura */
        isActiveRow?: boolean;
        /** Si la orden puede entregarse (muestra en Muestras = false) */
        canDeliver?: boolean;
    }>(),
    { activeProcess: '', isActiveRow: true, canDeliver: true },
);

const emit = defineEmits<{
    (e: 'timeline'): void;
    (e: 'novelty'): void;
    (e: 'deliver'): void;
    (e: 'technical-sheet'): void;
    (e: 'view-info'): void;
    (e: 'cut-consumption'): void;
    (e: 'logistics-supply-exit'): void;
}>();

const actionBtn =
    'inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';
const iconCls = 'h-4 w-4';
</script>

<template>
    <div class="inline-flex items-center gap-0.5">
        <!-- Ver información básica (siempre visible) -->
        <Button variant="ghost" size="icon" :class="actionBtn" title="Ver información básica" @click.stop="emit('view-info')" @pointerdown.stop>
            <Eye :class="iconCls" />
            <span class="sr-only">Ver información básica</span>
        </Button>

        <!-- Ficha técnica: en tab I&D, visible para todos (solo lectura si no es la fila activa) -->
        <Button
            v-if="activeProcess === 'I&D'"
            variant="ghost"
            size="icon"
            :class="[actionBtn, isActiveRow ? 'text-foreground' : 'text-muted-foreground/60']"
            :title="isActiveRow ? 'Ficha técnica' : 'Ver ficha técnica (solo lectura)'"
            @click.stop="emit('technical-sheet')"
            @pointerdown.stop
        >
            <ClipboardList :class="iconCls" />
            <span class="sr-only">{{ isActiveRow ? 'Ficha técnica' : 'Ver ficha técnica' }}</span>
        </Button>

        <!-- Novedades pendientes: acceso rápido (solo filas activas) -->
        <Button
            v-if="isActiveRow && row.has_pending_novelties"
            variant="ghost"
            size="icon"
            :class="[actionBtn, 'text-orange-600 hover:bg-orange-100 hover:text-orange-700']"
            title="Resolver novedades pendientes"
            @click.stop="emit('timeline')"
            @pointerdown.stop
        >
            <MessageSquareWarning :class="iconCls" />
            <span class="sr-only">Resolver novedades pendientes</span>
        </Button>

        <!-- Consumo de rollos (Corte): visible en tab Corte para todos -->
        <Button
            v-if="activeProcess === 'Corte'"
            variant="ghost"
            size="icon"
            :class="[actionBtn, isActiveRow ? 'text-primary hover:bg-primary/10 hover:text-primary' : 'text-muted-foreground/60 hover:bg-muted/40']"
            :title="isActiveRow ? 'Consumo rollos corte' : 'Ver consumo rollos corte (solo lectura)'"
            @click.stop="emit('cut-consumption')"
            @pointerdown.stop
        >
            <Scissors :class="iconCls" />
            <span class="sr-only">{{ isActiveRow ? 'Consumo rollos corte' : 'Ver consumo rollos corte' }}</span>
        </Button>

        <!-- Salida de insumos (Logística): visible en tab Logística para todos -->
        <Button
            v-if="activeProcess === 'Logística'"
            variant="ghost"
            size="icon"
            :class="[actionBtn, isActiveRow ? 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700' : 'text-muted-foreground/60 hover:bg-muted/40']"
            :title="isActiveRow ? 'Salida de insumos' : 'Ver salida de insumos (solo lectura)'"
            @click.stop="emit('logistics-supply-exit')"
            @pointerdown.stop
        >
            <PackageCheck :class="iconCls" />
            <span class="sr-only">{{ isActiveRow ? 'Salida de insumos' : 'Ver salida de insumos' }}</span>
        </Button>

        <!-- Menú de acciones: solo para filas activas -->
        <DropdownMenu v-if="isActiveRow">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" :class="actionBtn" title="Acciones" @click.stop @pointerdown.stop>
                    <MoreVertical :class="iconCls" />
                    <span class="sr-only">Acciones</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-52">
                <DropdownMenuItem class="cursor-pointer gap-2" @click="emit('timeline')">
                    <Activity class="h-4 w-4" />
                    Línea de tiempo
                </DropdownMenuItem>
                <DropdownMenuItem v-if="activeProcess === 'Corte'" class="cursor-pointer gap-2" @click="emit('cut-consumption')">
                    <Scissors class="h-4 w-4" />
                    Consumo rollos corte
                </DropdownMenuItem>
                <DropdownMenuItem v-if="activeProcess === 'Logística'" class="cursor-pointer gap-2 text-emerald-700" @click="emit('logistics-supply-exit')">
                    <PackageCheck class="h-4 w-4" />
                    Salida de insumos
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem class="cursor-pointer gap-2 text-orange-600" @click="emit('novelty')">
                    <Flag class="h-4 w-4" />
                    Reportar novedad
                </DropdownMenuItem>
                <DropdownMenuItem
                    :class="['gap-2', canDeliver ? 'cursor-pointer text-primary' : 'cursor-not-allowed opacity-60']"
                    :disabled="!canDeliver"
                    @click="canDeliver && emit('deliver')"
                >
                    <Send class="h-4 w-4" />
                    Entregar al siguiente
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
