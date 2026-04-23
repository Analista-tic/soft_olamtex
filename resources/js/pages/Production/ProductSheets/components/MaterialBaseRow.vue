<script setup lang="ts">
/**
 * Componente: MaterialBaseRow
 *
 * Fila del formulario que agrupa: toggle producto tejido, select base tela,
 * select base hilaza (condicional) y selector de tallas.
 *
 * Regla de negocio:
 *  - woven_product = false → 3 columnas: toggle, base tela*, tallas*
 *  - woven_product = true  → 4 columnas: toggle, base tela (opcional), base hilaza*, tallas*
 */
import HelpTooltip from '@/components/HelpTooltip.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import Label from '@/components/ui/label/Label.vue';
import type { OptionParams } from '@/domain/Production';
import type { Option } from '@/domain/shared';

interface Props {
    wovenProduct: boolean;
    fabricBaseId: number | null;
    yarnBaseId: number | null;
    sizes: string;
    fabricBaseOptions: OptionParams[];
    yarnBaseOptions: OptionParams[];
    sizeGroups: Record<string, string[]>;
    disabled: boolean;
    showFieldErrors: boolean;
    fabricBaseError?: string;
    yarnBaseError?: string;
    tooltipWovenProduct: string;
    tooltipBase: string;
    tooltipYarnBase: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:wovenProduct', value: boolean): void;
    (e: 'update:fabricBaseId', value: Option['id'] | Option['id'][] | null | undefined): void;
    (e: 'update:yarnBaseId', value: Option['id'] | Option['id'][] | null | undefined): void;
    (e: 'update:sizes', value: string): void;
    (e: 'search:fabricBase', term: string): void;
    (e: 'search:yarnBase', term: string): void;
    (e: 'create:fabricBase'): void;
    (e: 'create:yarnBase'): void;
}>();

const gridClass = computed(() =>
    `mb-4 grid gap-4 ${props.wovenProduct ? 'grid-cols-4' : 'grid-cols-3'}`,
);
</script>

<script lang="ts">
import { computed } from 'vue';
</script>

<template>
    <div :class="gridClass">
        <!-- Toggle Producto Tejido -->
        <div class="flex flex-col justify-end gap-1">
            <div class="flex items-center gap-2">
                <Label class="text-sm font-medium">Producto tejido</Label>
                <HelpTooltip :text="tooltipWovenProduct" aria-label="Ayuda sobre Producto tejido" />
            </div>
            <div class="flex h-10 items-center">
                <SwitchField
                    :model-value="wovenProduct"
                    label=""
                    id="woven_product"
                    :disabled="disabled"
                    @update:model-value="emit('update:wovenProduct', $event)"
                />
            </div>
        </div>

        <!-- Base Tela -->
        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Label class="text-sm font-medium">
                    Base Tela<span v-if="!wovenProduct" class="text-red-500">*</span>
                </Label>
                <HelpTooltip :text="tooltipBase" aria-label="Ayuda sobre Base Tela" />
            </div>
            <SelectField
                class="w-full"
                :model-value="fabricBaseId"
                label=""
                :options="fabricBaseOptions"
                :required="!wovenProduct"
                placeholder="Seleccione base tela"
                :disabled="disabled"
                :searchable="true"
                :search-threshold="0"
                action-label="Agregar base tela"
                :action-disabled="disabled"
                @update:model-value="emit('update:fabricBaseId', $event)"
                @search="(term: string | number | null) => emit('search:fabricBase', String(term ?? ''))"
                @action="emit('create:fabricBase')"
                :invalid="showFieldErrors && !!fabricBaseError"
                :error-message="showFieldErrors ? fabricBaseError : undefined"
            />
        </div>

        <!-- Base Hilaza (solo si es producto tejido) -->
        <div v-if="wovenProduct" class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Label class="text-sm font-medium">Base hilaza<span class="text-red-500">*</span></Label>
                <HelpTooltip :text="tooltipYarnBase" aria-label="Ayuda sobre Base hilaza" />
            </div>
            <SelectField
                class="w-full"
                :model-value="yarnBaseId"
                label=""
                :options="yarnBaseOptions"
                required
                placeholder="Seleccione base hilaza"
                :disabled="disabled"
                :searchable="true"
                :search-threshold="0"
                action-label="Agregar base hilaza"
                :action-disabled="disabled"
                @update:model-value="emit('update:yarnBaseId', $event)"
                @search="(term: string | number | null) => emit('search:yarnBase', String(term ?? ''))"
                @action="emit('create:yarnBase')"
                :invalid="showFieldErrors && !!yarnBaseError"
                :error-message="showFieldErrors ? yarnBaseError : undefined"
            />
        </div>

        <!-- Tallas -->
        <div class="flex flex-col justify-end gap-1">
            <div class="tooltip-managed-size-selector">
                <SizeSelector
                    class="w-full"
                    :model-value="sizes"
                    :sizeGroups="sizeGroups"
                    :disabled="disabled"
                    @update:model-value="emit('update:sizes', $event)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.tooltip-managed-size-selector :deep(.mb-2) {
    display: none;
}
</style>
