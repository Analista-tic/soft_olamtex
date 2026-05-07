<script setup lang="ts">
import axios from 'axios';
import { useClipboard } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { hexToRgb } from '@/lib/colorUtils';
import { autoUpdate, computePosition, flip, offset, shift } from '@floating-ui/dom';
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';
import { Check, ChevronDown, Copy, Plus } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type ColorOption = {
    id: number;
    name: string;
    rgb: string | null;
    hexadecimal: string | null;
    description: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: number | string | null;
        label?: string;
        disabled?: boolean;
    }>(),
    { label: 'Color', disabled: false },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'selected-color', value: ColorOption | null): void;
}>();

const colors = ref<ColorOption[]>([]);
const isLoading = ref(false);
const searchTerm = ref('');

const isCreating = ref(false);
const createName = ref('');
const createHex = ref('#9ca3af');
const createDescription = ref('');
const createError = ref('');
const isSaving = ref(false);
const inlineInputRef = ref<HTMLInputElement | null>(null);

const colorNameInputId = `color-name-${Math.random().toString(36).slice(2)}`;
const colorHexInputId = `color-hex-${Math.random().toString(36).slice(2)}`;

const NO_APLICA_ID = '';

const colorOptions = computed(() => [
    { id: NO_APLICA_ID, name: 'No aplica' },
    ...colors.value.map((c) => ({ id: String(c.id), name: c.name })),
]);

const filteredOptions = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return colorOptions.value;
    return colorOptions.value.filter((o) => o.name.toLowerCase().includes(term));
});

const selectedColorData = computed<ColorOption | null>(() => {
    if (
        props.modelValue === null ||
        props.modelValue === undefined ||
        String(props.modelValue).trim() === ''
    ) return null;
    return colors.value.find((c) => String(c.id) === String(props.modelValue)) ?? null;
});

const comboboxValue = computed(() => {
    if (!props.modelValue) return colorOptions.value[0] ?? null;
    return colorOptions.value.find(
        (o) => o.id !== NO_APLICA_ID && o.id === String(props.modelValue)
    ) ?? colorOptions.value[0] ?? null;
});

function handleComboboxChange(option: { id: string; name: string } | null) {
    if (!option || option.id === NO_APLICA_ID) {
        emit('update:modelValue', null);
        emit('selected-color', null);
        return;
    }
    const numId = Number(option.id);
    emit('update:modelValue', numId);
    const color = colors.value.find((c) => c.id === numId) ?? null;
    emit('selected-color', color);
}

function handleInput(event: Event) {
    if (isCreating.value) return;
    searchTerm.value = (event.target as HTMLInputElement).value;
}

function colorStyle(rgb: string | null) {
    return { backgroundColor: rgb ? `rgb(${rgb})` : '#9ca3af' };
}

const createPreviewRgb = computed(() => hexToRgb(createHex.value));

type ColorFormat = 'hex' | 'rgb' | 'hsl';
const colorFormat = ref<ColorFormat>('hex');

const { copy: copyToClipboard } = useClipboard();

function hexToRgbValues(hex: string): { r: number; g: number; b: number } | null {
    const rgb = hexToRgb(hex);
    if (!rgb) return null;
    const [r, g, b] = rgb.split(',').map(Number);
    return { r, g, b };
}

function rgbToHsl(r: number, g: number, b: number): { h: number; s: number; l: number } {
    const rn = r / 255, gn = g / 255, bn = b / 255;
    const max = Math.max(rn, gn, bn), min = Math.min(rn, gn, bn);
    let h = 0, s = 0;
    const l = (max + min) / 2;
    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case rn: h = ((gn - bn) / d + (gn < bn ? 6 : 0)) / 6; break;
            case gn: h = ((bn - rn) / d + 2) / 6; break;
            case bn: h = ((rn - gn) / d + 4) / 6; break;
        }
    }
    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

function parseRgbString(value: string): string | null {
    const match = value.trim().match(
        /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i
    );
    if (!match) return null;
    const r = parseInt(match[1]), g = parseInt(match[2]), b = parseInt(match[3]);
    if (r > 255 || g > 255 || b > 255) return null;
    return '#' + [r, g, b]
        .map((v) => v.toString(16).padStart(2, '0'))
        .join('');
}

function parseHslString(value: string): string | null {
    const match = value.trim().match(
        /^hsl\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%\s*\)$/i
    );
    if (!match) return null;
    const h = parseInt(match[1]) / 360;
    const s = parseInt(match[2]) / 100;
    const l = parseInt(match[3]) / 100;
    const hue2rgb = (p: number, q: number, t: number) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
    };
    let r: number, g: number, b: number;
    if (s === 0) {
        r = g = b = l;
    } else {
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
    }
    return '#' + [r, g, b]
        .map((v) => Math.round(v * 255).toString(16).padStart(2, '0'))
        .join('');
}

function handleColorInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value.trim();
    if (!raw) return;

    if (colorFormat.value === 'hex') {
        const normalized = raw.startsWith('#') ? raw : `#${raw}`;
        if (/^#[0-9a-fA-F]{6}$/.test(normalized)) {
            createHex.value = normalized;
        }
        return;
    }

    if (colorFormat.value === 'rgb') {
        const hex = parseRgbString(raw);
        if (hex) createHex.value = hex;
        return;
    }

    if (colorFormat.value === 'hsl') {
        const hex = parseHslString(raw);
        if (hex) createHex.value = hex;
    }
}

const colorFormattedValue = computed(() => {
    const rgb = hexToRgbValues(createHex.value);
    if (!rgb) return createHex.value.toUpperCase();
    if (colorFormat.value === 'hex') return createHex.value.toUpperCase();
    if (colorFormat.value === 'rgb') return `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    return `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`;
});

const triggerColorStyle = computed(() => ({
    backgroundColor: selectedColorData.value?.rgb
        ? `rgb(${selectedColorData.value.rgb})`
        : '#9ca3af',
}));

async function fetchColors() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/colors');
        colors.value = Array.isArray(data) ? data : [];
    } catch {
        colors.value = [];
    } finally {
        isLoading.value = false;
    }
}

onMounted(fetchColors);

function openInlineCreate() {
    createName.value = '';
    createHex.value = '#9ca3af';
    createError.value = '';
    isCreating.value = true;
    setTimeout(() => {
        inlineInputRef.value?.focus();
    }, 80);
}

function cancelInlineCreate() {
    if (isSaving.value) return;
    isCreating.value = false;
    createName.value = '';
    createHex.value = '#9ca3af';
    createDescription.value = '';
    createError.value = '';
    colorFormat.value = 'hex';
    searchTerm.value = '';
}

async function submitInlineCreate() {
    if (isSaving.value) return;
    createError.value = '';

    if (!createName.value.trim()) {
        createError.value = 'El nombre es obligatorio.';
        nextTick(() => inlineInputRef.value?.focus());
        return;
    }

    const rgb = createPreviewRgb.value;
    if (!rgb) {
        createError.value = 'Color inválido.';
        return;
    }

    isSaving.value = true;
    try {
        const { data } = await axios.post('/colors', {
            name: createName.value.trim(),
            rgb,
            hexadecimal: createHex.value.toUpperCase(),
            ...(createDescription.value.trim()
                ? { description: createDescription.value.trim() }
                : {}),
        });
        await fetchColors();
        isCreating.value = false;
        createName.value = '';
        createHex.value = '#9ca3af';
        createDescription.value = '';
        createError.value = '';
        colorFormat.value = 'hex';
        searchTerm.value = '';
        const newId = Number(data.id);
        emit('update:modelValue', newId);
        const created = colors.value.find((c) => c.id === newId) ?? null;
        emit('selected-color', created);
    } catch (error: any) {
        createError.value =
            error?.response?.data?.message || 'No se pudo crear el color.';
    } finally {
        isSaving.value = false;
    }
}

const triggerRef = ref<HTMLElement | null>(null);
const floatingRef = ref<HTMLElement | null>(null);
const floatingStyles = ref<Record<string, string>>({
    position: 'fixed',
    left: '0px',
    top: '0px',
    width: 'auto',
    minWidth: '100px',
    pointerEvents: 'auto',
    zIndex: '99999',
});
let floatingCleanup: ReturnType<typeof autoUpdate> | null = null;

async function updateFloatingPosition() {
    if (!triggerRef.value || !floatingRef.value) return;
    try {
        const rect = triggerRef.value.getBoundingClientRect();
        const { x, y } = await computePosition(triggerRef.value, floatingRef.value, {
            strategy: 'fixed',
            placement: 'bottom-start',
            middleware: [offset(4), flip({ padding: 8 }), shift({ padding: 8 })],
        });
        const width = Math.max(rect.width, 120);
        floatingStyles.value = {
            position: 'fixed',
            left: `${x}px`,
            top: `${y}px`,
            width: `${width}px`,
            minWidth: `${width}px`,
            pointerEvents: 'auto',
            zIndex: '99999',
        };
    } catch (e) {
        console.warn('Error positioning color dropdown', e);
    }
}

function startFloatingUpdates() {
    if (floatingCleanup || !triggerRef.value || !floatingRef.value) return;
    floatingCleanup = autoUpdate(
        triggerRef.value,
        floatingRef.value,
        updateFloatingPosition,
        { ancestorScroll: true, ancestorResize: true, elementResize: true, animationFrame: true },
    );
    void updateFloatingPosition();
}

function stopFloatingUpdates() {
    if (floatingCleanup) {
        floatingCleanup();
        floatingCleanup = null;
    }
}

watch(() => floatingRef.value, (el) => {
    if (el && triggerRef.value) nextTick(startFloatingUpdates);
    else stopFloatingUpdates();
});

onBeforeUnmount(stopFloatingUpdates);
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}</Label>

        <Combobox
            :modelValue="comboboxValue"
            :disabled="disabled"
            by="id"
            @update:modelValue="handleComboboxChange"
        >
            <template #default="{ open }">
                <div ref="triggerRef" class="relative">
                    <ComboboxInput
                        :class="[
                            'h-10 min-h-[42px] w-full rounded-md border border-input',
                            'bg-background py-2 pr-10 text-sm text-foreground',
                            'shadow-sm transition',
                            'placeholder:text-muted-foreground',
                            'focus-visible:outline-none focus-visible:ring-2',
                            'focus-visible:ring-ring focus-visible:ring-offset-2',
                            'focus-visible:ring-offset-background',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                            selectedColorData ? 'pl-8' : 'pl-3',
                        ]"
                        placeholder="Selecciona un color"
                        :displayValue="(opt) => (opt as { id: string; name: string } | null)?.name ?? ''"
                        :disabled="disabled || isCreating"
                        @input="handleInput"
                    />

                    <span
                        v-if="selectedColorData"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 inline-block h-4 w-4 shrink-0 rounded-full border border-slate-200"
                        :style="triggerColorStyle"
                    />

                    <ComboboxButton
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground/70"
                        :disabled="disabled || isCreating"
                    >
                        <ChevronDown class="h-4 w-4" />
                    </ComboboxButton>

                    <Teleport to="body">
                        <div
                            v-if="open || isCreating"
                            ref="floatingRef"
                            :style="floatingStyles"
                            class="z-[99999]"
                            @pointerdown.stop
                            @click.stop
                        >
                            <ComboboxOptions
                                v-show="!isCreating"
                                static
                                class="relative max-h-52 overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-2xl focus:outline-none"
                            >
                                <div class="sticky top-0 z-10 border-b border-border bg-popover px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="disabled"
                                        @mousedown.prevent
                                        @click.stop.prevent="openInlineCreate"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Nuevo color
                                    </button>
                                </div>

                                <ComboboxOption
                                    v-for="option in filteredOptions"
                                    :key="option.id"
                                    :value="option"
                                >
                                    <template #default="{ active, selected }">
                                        <div
                                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm transition-colors"
                                            :class="active
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-popover-foreground'"
                                        >
                                            <span
                                                v-if="option.id !== ''"
                                                class="inline-block h-4 w-4 shrink-0 rounded-full border border-slate-200"
                                                :style="colorStyle(
                                                    colors.find(c => String(c.id) === option.id)?.rgb ?? null
                                                )"
                                            />
                                            <span
                                                v-else
                                                class="inline-block h-4 w-4 shrink-0"
                                            />
                                            <span class="flex-1 truncate">{{ option.name }}</span>
                                            <Check
                                                v-if="selected"
                                                class="h-4 w-4 shrink-0"
                                            />
                                        </div>
                                    </template>
                                </ComboboxOption>

                                <div
                                    v-if="isLoading"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    Cargando colores...
                                </div>
                                <div
                                    v-else-if="filteredOptions.length === 1 && searchTerm"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    No se encontraron colores.
                                </div>
                            </ComboboxOptions>

                            <div
                                v-if="isCreating"
                                class="rounded-md border border-border bg-popover text-popover-foreground shadow-2xl"
                            >
                                <div class="space-y-3 px-3 py-3">
                                    <div class="grid gap-1.5">
                                        <Label :for="colorNameInputId" class="text-xs">
                                            Nombre
                                        </Label>
                                        <input
                                            :id="colorNameInputId"
                                            ref="inlineInputRef"
                                            v-model="createName"
                                            type="text"
                                            placeholder="Nombre del color"
                                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="isSaving"
                                            @keydown.enter.prevent="submitInlineCreate"
                                            @keydown.esc.prevent="cancelInlineCreate"
                                        />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label class="text-xs">Descripción (opcional)</Label>
                                        <input
                                            v-model="createDescription"
                                            type="text"
                                            placeholder="Descripción del color"
                                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="isSaving"
                                            @keydown.enter.prevent="submitInlineCreate"
                                            @keydown.esc.prevent="cancelInlineCreate"
                                        />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label class="text-xs">Color</Label>

                                        <label
                                            :for="colorHexInputId"
                                            class="relative block h-20 w-full cursor-pointer overflow-hidden rounded-t-md border border-b-0 border-input shadow-sm"
                                            :style="{ backgroundColor: createPreviewRgb ? `rgb(${createPreviewRgb})` : '#9ca3af' }"
                                        >
                                            <input
                                                :id="colorHexInputId"
                                                v-model="createHex"
                                                type="color"
                                                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                :disabled="isSaving"
                                            />
                                        </label>

                                        <div
                                            class="flex items-center gap-0 overflow-hidden rounded-b-md border border-input shadow-sm"
                                        >
                                            <input
                                                type="text"
                                                :value="colorFormattedValue"
                                                class="flex-1 min-w-0 bg-background px-3 py-2 font-mono text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="isSaving"
                                                spellcheck="false"
                                                autocomplete="off"
                                                @change="handleColorInput"
                                                @keydown.enter.prevent="handleColorInput"
                                                @mousedown.stop
                                                @click.stop
                                            />

                                            <button
                                                type="button"
                                                class="shrink-0 border-l border-input bg-background p-2 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                :title="`Copiar ${colorFormat.toUpperCase()}`"
                                                @mousedown.prevent
                                                @click.stop.prevent="copyToClipboard(colorFormattedValue)"
                                            >
                                                <Copy class="h-3.5 w-3.5" />
                                            </button>

                                            <div class="relative shrink-0 border-l border-input">
                                                <select
                                                    v-model="colorFormat"
                                                    class="h-full appearance-none bg-background py-2 pl-3 pr-7 text-xs font-medium text-foreground focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                                                    @mousedown.stop
                                                    @click.stop
                                                >
                                                    <option value="hex">Hex</option>
                                                    <option value="rgb">RGB</option>
                                                    <option value="hsl">HSL</option>
                                                </select>
                                                <ChevronDown
                                                    class="pointer-events-none absolute right-1.5 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <p v-if="createError" class="text-xs text-destructive">
                                        {{ createError }}
                                    </p>

                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="pill"
                                            class="border-primary text-primary hover:bg-primary/10 hover:text-primary"
                                            :disabled="isSaving"
                                            @mousedown.prevent
                                            @click.stop.prevent="cancelInlineCreate"
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="pill"
                                            :disabled="isSaving || !createName.trim()"
                                            @mousedown.prevent
                                            @click.stop.prevent="submitInlineCreate"
                                        >
                                            {{ isSaving ? 'Creando...' : 'Crear' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Teleport>
                </div>
            </template>
        </Combobox>
    </div>
</template>
