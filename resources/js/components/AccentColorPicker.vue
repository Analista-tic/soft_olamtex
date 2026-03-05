<script setup lang="ts">
import { useAccentColor, type AccentColor } from '@/composables/useAccentColor';

const { accentColor, updateAccentColor } = useAccentColor();

const options: Array<{ value: AccentColor; label: string; swatchClass: string }> = [
    { value: 'zinc', label: 'Zinc', swatchClass: 'bg-zinc-600' },
    { value: 'red', label: 'Rojo', swatchClass: 'bg-red-500' },
    { value: 'rose', label: 'Rosa', swatchClass: 'bg-rose-300' },
    { value: 'orange', label: 'Naranja', swatchClass: 'bg-orange-500' },
    { value: 'green', label: 'Verde', swatchClass: 'bg-green-500' },
    { value: 'blue', label: 'Azul', swatchClass: 'bg-blue-500' },
    { value: 'yellow', label: 'Amarillo', swatchClass: 'bg-yellow-400' },
    { value: 'violet', label: 'Violeta', swatchClass: 'bg-violet-500' },
];
</script>

<template>
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium">Color del tema</h3>
            <p class="text-xs text-muted-foreground">Personaliza el color de acento sin cambiar el modo claro/oscuro.</p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <button
                v-for="{ value, label, swatchClass } in options"
                :key="value"
                type="button"
                @click="updateAccentColor(value)"
                :class="[
                    'flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors',
                    accentColor === value
                        ? 'border-primary ring-2 ring-ring ring-offset-2 ring-offset-background'
                        : 'border-border hover:border-primary/50',
                ]"
            >
                <span :class="['h-4 w-4 rounded-full border border-black/10 dark:border-white/15', swatchClass]" />
                <span>{{ label }}</span>
            </button>
        </div>
    </div>
</template>
