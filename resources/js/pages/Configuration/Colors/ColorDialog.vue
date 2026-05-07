<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Label } from '@/components/ui/label';
import { Copy } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useClipboard } from '@vueuse/core';

type ColorForm = {
    id?: number | null;
    name: string;
    hex: string;
    description: string;
};

function createColorForm(color?: Partial<ColorForm>): ColorForm {
    return {
        id: color?.id ?? null,
        name: color?.name ?? '',
        hex: color?.hex ?? '#9ca3af',
        description: color?.description ?? '',
    };
}

const props = withDefaults(defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: ColorForm;
    loading?: boolean;
}>(), { loading: false });

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', form: ColorForm): void;
    (e: 'cancel'): void;
}>();

const internalForm = ref<ColorForm>(createColorForm(props.form));

watch(() => props.form, (val) => {
    internalForm.value = createColorForm(val);
}, { deep: true });

watch(() => props.open, (val) => {
    if (val && props.dialogType === 'create') {
        internalForm.value = createColorForm();
    }
});

const { copy: copyToClipboard } = useClipboard();

type ColorFormat = 'hex' | 'rgb' | 'hsl';
const colorFormat = ref<ColorFormat>('hex');

function hexToRgb(hex: string): string | null {
    const n = hex.trim().replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(n)) return null;
    return `${parseInt(n.slice(0, 2), 16)},${parseInt(n.slice(2, 4), 16)},${parseInt(n.slice(4, 6), 16)}`;
}

function hexToRgbValues(hex: string): { r: number; g: number; b: number } | null {
    const rgb = hexToRgb(hex);
    if (!rgb) return null;
    const [r, g, b] = rgb.split(',').map(Number);
    return { r, g, b };
}

function rgbToHsl(r: number, g: number, b: number): { h: number; s: number; l: number } {
    const rn = r / 255;
    const gn = g / 255;
    const bn = b / 255;
    const max = Math.max(rn, gn, bn);
    const min = Math.min(rn, gn, bn);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case rn:
                h = ((gn - bn) / d + (gn < bn ? 6 : 0)) / 6;
                break;
            case gn:
                h = ((bn - rn) / d + 2) / 6;
                break;
            case bn:
                h = ((rn - gn) / d + 4) / 6;
                break;
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
    const r = parseInt(match[1]);
    const g = parseInt(match[2]);
    const b = parseInt(match[3]);
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
    let r: number;
    let g: number;
    let b: number;
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
            internalForm.value.hex = normalized;
        }
        return;
    }

    if (colorFormat.value === 'rgb') {
        const hex = parseRgbString(raw);
        if (hex) internalForm.value.hex = hex;
        return;
    }

    if (colorFormat.value === 'hsl') {
        const hex = parseHslString(raw);
        if (hex) internalForm.value.hex = hex;
    }
}

const colorFormattedValue = computed(() => {
    const rgb = hexToRgbValues(internalForm.value.hex);
    if (!rgb) return internalForm.value.hex.toUpperCase();
    if (colorFormat.value === 'hex') return internalForm.value.hex.toUpperCase();
    if (colorFormat.value === 'rgb') return `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    return `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`;
});

const colorHexInputId = `color-hex-dialog-${Math.random().toString(36).slice(2)}`;

function cancel() {
    colorFormat.value = 'hex';
    emit('cancel');
    emit('update:open', false);
}

function submit() {
    if (!internalForm.value.name.trim()) return;
    colorFormat.value = 'hex';
    emit('submit', { ...internalForm.value });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[95vh] max-w-lg flex-col px-2 py-2">
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Nuevo color' : 'Editar color' }}
                </DialogTitle>
            </DialogHeader>

            <form class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin" @submit.prevent="submit">
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="color-name">Nombre</Label>
                        <Input
                            id="color-name"
                            v-model="internalForm.name"
                            placeholder="Nombre del color"
                            required
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Color</Label>

                        <label
                            :for="colorHexInputId"
                            class="relative block h-20 w-full cursor-pointer overflow-hidden rounded-t-md border border-b-0 border-input shadow-sm"
                            :style="{ backgroundColor: hexToRgb(internalForm.hex) ? `rgb(${hexToRgb(internalForm.hex)})` : '#9ca3af' }"
                        >
                            <input
                                :id="colorHexInputId"
                                v-model="internalForm.hex"
                                type="color"
                                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                :disabled="props.loading"
                            />
                        </label>

                        <div class="flex items-center gap-0 overflow-hidden rounded-b-md border border-input shadow-sm">
                            <input
                                type="text"
                                :value="colorFormattedValue"
                                class="flex-1 min-w-0 bg-background px-3 py-2 font-mono text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="props.loading"
                                spellcheck="false"
                                autocomplete="off"
                                @change="handleColorInput"
                                @keydown.enter.prevent="handleColorInput"
                            />

                            <button
                                type="button"
                                class="shrink-0 border-l border-input bg-background p-2 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                :title="`Copiar ${colorFormat.toUpperCase()}`"
                                @click.prevent="copyToClipboard(colorFormattedValue)"
                            >
                                <Copy class="h-3.5 w-3.5" />
                            </button>

                            <div class="relative shrink-0 border-l border-input">
                                <select
                                    v-model="colorFormat"
                                    class="h-full appearance-none bg-background py-2 pl-3 pr-7 text-xs font-medium text-foreground focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <option value="hex">Hex</option>
                                    <option value="rgb">RGB</option>
                                    <option value="hsl">HSL</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="color-description">Descripción</Label>
                        <Input
                            id="color-description"
                            v-model="internalForm.description"
                            placeholder="Descripción"
                        />
                    </div>
                </div>
            </form>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button
                    variant="outline"
                    size="pill"
                    class="border-primary text-primary hover:bg-primary/10 hover:text-primary"
                    @click="cancel"
                >
                    Cancelar
                </Button>
                <Button variant="default" size="pill" :disabled="props.loading" @click="submit">
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
