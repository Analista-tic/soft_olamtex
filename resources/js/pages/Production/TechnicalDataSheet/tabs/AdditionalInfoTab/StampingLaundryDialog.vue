<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Image as ImageIcon, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Attachment = { id: string; name: string; url: string };
type StampingData = {
    designNotes?: string | null;
    attachments?: Attachment[];
};
type CuttingGuideData = {
    sizes?: Array<{ uid?: string; size: string; units: number; curve?: string | null }>;
};
type BasicInfoItem = { label: string; value: string };
type StampingProcessGuide = {
    supplier: string | null;
    price: number | null;
    processType: string | null;
};
type StampingMeta = {
    processes: string;
    observation: string;
};
type ColorSlot = {
    label: string;
    name: string;
    hex: string | null;
};

const LEGACY_STAMPING_META_PREFIX = '@stamping_meta:';
const IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const PROCESS_CHAR_LIMIT = 500;
const OBSERVATION_CHAR_LIMIT = 800;
const IMAGE_MAX_WIDTH = 1400;

function makeId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

function parseStampingMeta(value: string | null | undefined): StampingMeta {
    const raw = String(value ?? '').trim();
    if (!raw) return { processes: '', observation: '' };

    if (raw.startsWith(LEGACY_STAMPING_META_PREFIX)) {
        try {
            const parsed = JSON.parse(raw.slice(LEGACY_STAMPING_META_PREFIX.length)) as { processes?: unknown; observation?: unknown };
            return {
                processes: String(parsed.processes ?? ''),
                observation: String(parsed.observation ?? ''),
            };
        } catch {
            return { processes: '', observation: '' };
        }
    }

    const match = raw.match(/PROCESOS:\s*([\s\S]*?)\s*OBSERVACIÓN:\s*([\s\S]*)/i);
    if (match) {
        return {
            processes: String(match[1] ?? '').trim(),
            observation: String(match[2] ?? '').trim(),
        };
    }

    return { processes: '', observation: raw };
}

function serializeStampingMeta(meta: StampingMeta): string | null {
    const processes = normalizeText(meta.processes) ?? '';
    const observation = normalizeText(meta.observation) ?? '';
    if (!processes && !observation) return null;
    return `PROCESOS:\n${processes}\n\nOBSERVACIÓN:\n${observation}`;
}

function formatCOP(value: number | null): string {
    if (value === null || value === undefined) return 'No definido';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Math.max(0, Number(value || 0)));
}

function sanitizeHex(value: unknown): string | null {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const normalized = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(normalized) ? normalized : null;
}

function blobToDataUrl(blob: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result ?? ''));
        reader.onerror = () => reject(reader.error ?? new Error('No se pudo leer la imagen'));
        reader.readAsDataURL(blob);
    });
}

function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('No se pudo abrir la imagen'));
        };
        img.src = objectUrl;
    });
}

async function optimizeImage(file: File): Promise<Blob> {
    const img = await loadImage(file);
    const scale = Math.min(1, IMAGE_MAX_WIDTH / img.width);
    const width = Math.max(1, Math.round(img.width * scale));
    const height = Math.max(1, Math.round(img.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('No se pudo preparar la imagen');
    ctx.drawImage(img, 0, 0, width, height);

    const outputType = file.type === 'image/png' ? 'image/jpeg' : 'image/webp';
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, outputType, 0.82));
    if (!blob) throw new Error('No se pudo optimizar la imagen');
    return blob;
}

const props = defineProps<{
    open: boolean;
    data: StampingData;
    displayName?: string;
    saveSection?: 'stamping' | 'laundry';
    hideProcesses?: boolean;
    savingSection: string | null;
    cuttingData?: CuttingGuideData;
    basicInfoItems?: BasicInfoItem[] | null;
    stampingProcessGuide?: StampingProcessGuide | null;
    drawingUrl?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:data', value: StampingData): void;
    (e: 'save', section: 'stamping' | 'laundry'): void;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const imageError = ref<string | null>(null);
const processingImage = ref(false);
const isDrawingPreviewOpen = ref(false);

const localData = computed({
    get: () => props.data,
    set: (value: StampingData) => emit('update:data', value),
});
const displayName = computed(() => (props.displayName?.trim() ? props.displayName.trim() : 'Estampado'));
const displayNameLower = computed(() => displayName.value.toLowerCase());
const effectiveSaveSection = computed<'stamping' | 'laundry'>(() => props.saveSection ?? 'stamping');
const isLaundryMode = computed(() => Boolean(props.hideProcesses) || effectiveSaveSection.value === 'laundry');

const meta = computed(() => parseStampingMeta(localData.value.designNotes));
const processesLength = computed(() => meta.value.processes.length);
const observationValue = computed(() => (isLaundryMode.value ? String(localData.value.designNotes ?? '') : meta.value.observation));
const observationLength = computed(() => observationValue.value.length);
const imageAttachment = computed(() => (Array.isArray(localData.value.attachments) ? (localData.value.attachments[0] ?? null) : null));
const guideRows = computed(() => (props.cuttingData?.sizes ?? []).filter((row) => String(row.size ?? '').trim() !== ''));
const totalGuideUnits = computed(() => guideRows.value.reduce((acc, row) => acc + Math.max(0, Number(row.units || 0)), 0));
const resolvedDrawingUrl = computed(() => {
    const raw = String(props.drawingUrl ?? '').trim();
    if (!raw) return null;
    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;
    if (raw.startsWith('/')) return raw;
    return `/${raw}`;
});
const basicInfoItemsResolved = computed(() => (props.basicInfoItems ?? []).map((item) => ({ ...item, value: String(item.value ?? '-') || '-' })));
const stampingProcess = computed(() => props.stampingProcessGuide ?? { supplier: null, price: null, processType: null });
const hasStampingProcess = computed(() => Boolean(stampingProcess.value.processType));
const headerDescription = computed(() =>
    isLaundryMode.value
        ? `Configura contexto, observaciones y guias visuales de ${displayNameLower.value}.`
        : `Configura contexto, procesos, observaciones y guias visuales de ${displayNameLower.value}.`,
);
const colorSlots = computed<ColorSlot[]>(() => {
    const source = localData.value as Record<string, unknown>;
    const candidateLists = [source.colorCodes, source.colors, source.colours];
    const list = candidateLists.find((entry) => Array.isArray(entry)) as Array<Record<string, unknown>> | undefined;

    if (!list || list.length === 0) return [];

    return list
        .filter((item) => String(item.name ?? item.code ?? item.label ?? '').trim() !== '')
        .map((item, index) => {
            const name = String(item.name ?? item.code ?? item.label ?? '').trim();
            const hex = sanitizeHex(item.hex ?? item.color ?? item.value);
            return {
                label: `Color ${index + 1}`,
                name,
                hex,
            };
        });
});
const orderedBasicInfoItems = computed(() => {
    const items = basicInfoItemsResolved.value;
    const normalize = (value: string) =>
        String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    const visibleItems = items.filter((item) => {
        const label = normalize(item.label);
        return !label.includes('referencia cliente / almacenes');
    });
    const matches = (label: string, terms: string[]) => terms.some((term) => label.includes(term));
    const take = (terms: string[], used: Set<number>): number =>
        visibleItems.findIndex((item, index) => !used.has(index) && matches(normalize(item.label), terms));

    const used = new Set<number>();
    const sorted: BasicInfoItem[] = [];
    [['orden de produccion', 'op'], ['base'], ['disenador']].forEach((terms) => {
        const idx = take(terms, used);
        if (idx >= 0) {
            sorted.push(visibleItems[idx]);
            used.add(idx);
        }
    });

    const rest = visibleItems.filter((_, index) => !used.has(index));
    return [...sorted, ...rest];
});

function emitMeta(next: StampingMeta): void {
    emit('update:data', {
        ...localData.value,
        designNotes: serializeStampingMeta(next),
    });
}

function updateProcesses(value: string): void {
    emitMeta({
        ...meta.value,
        processes: String(value ?? '').slice(0, PROCESS_CHAR_LIMIT),
    });
}

function updateObservation(value: string): void {
    if (isLaundryMode.value) {
        emit('update:data', {
            ...localData.value,
            designNotes: normalizeText(String(value ?? '').slice(0, OBSERVATION_CHAR_LIMIT)),
        });
        return;
    }

    emitMeta({
        ...meta.value,
        observation: String(value ?? '').slice(0, OBSERVATION_CHAR_LIMIT),
    });
}

function openFilePicker(): void {
    imageError.value = null;
    fileInputRef.value?.click();
}

function openDrawingPreview(): void {
    if (!resolvedDrawingUrl.value) return;
    isDrawingPreviewOpen.value = true;
}

async function onFileSelected(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0];
    if (!file) return;

    imageError.value = null;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        imageError.value = 'Formato no permitido. Usa JPG, PNG o WEBP.';
        if (target) target.value = '';
        return;
    }
    if (file.size > IMAGE_MAX_BYTES) {
        imageError.value = 'La imagen supera 2MB. Selecciona una más liviana.';
        if (target) target.value = '';
        return;
    }

    processingImage.value = true;
    try {
        const optimized = await optimizeImage(file);
        const url = await blobToDataUrl(optimized);
        const ext = optimized.type === 'image/webp' ? 'webp' : 'jpg';
        emit('update:data', {
            ...localData.value,
            attachments: [{ id: makeId(), name: `stamping-image.${ext}`, url }],
        });
    } catch (error) {
        console.error('Error al procesar imagen de estampado:', error);
        imageError.value = 'No fue posible procesar la imagen.';
    } finally {
        processingImage.value = false;
        if (target) target.value = '';
    }
}

function removeImage(): void {
    imageError.value = null;
    emit('update:data', { ...localData.value, attachments: [] });
}

function handleSave(): void {
    emit('save', effectiveSaveSection.value);
}

function handleClose(): void {
    emit('close');
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) handleClose();
}
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[96vh] max-w-[1150px] flex-col px-2 py-2 data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ displayName }}</DialogTitle>
                <DialogDescription>{{ headerDescription }}</DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <div class="mb-3">
                                <p class="text-sm font-semibold text-foreground">Información base</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="item in orderedBasicInfoItems" :key="item.label" class="space-y-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ item.label }}</p>
                                    <p class="text-sm font-medium text-foreground">{{ item.value }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-border pt-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Proveedor</p>
                                    <p class="text-sm font-medium text-foreground">
                                        {{ hasStampingProcess ? (stampingProcess.supplier ?? 'No definido') : 'No definido' }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Precio</p>
                                    <p class="text-sm font-medium text-foreground">
                                        {{ hasStampingProcess ? formatCOP(stampingProcess.price) : 'No definido' }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="!hasStampingProcess" class="mt-2 text-xs text-muted-foreground">Configúralo en los Procesos.</p>
                        </div>

                        <div class="mt-4 border-t border-border pt-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-sm font-semibold text-foreground">Tallas</p>
                                </div>
                                <Badge variant="outline">Total unidades: {{ totalGuideUnits }}</Badge>
                            </div>

                            <div
                                v-if="guideRows.length === 0"
                                class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground"
                            >
                                Aún no hay datos de Corte registrados. Guarda Corte para ver esta guía.
                            </div>

                            <div v-else class="overflow-x-auto rounded-md border border-border">
                                <Table class="min-w-[420px] text-center">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Talla</TableHead>
                                            <TableHead>Curva</TableHead>
                                            <TableHead>Unidades estimadas</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="row in guideRows" :key="row.uid ?? row.size">
                                            <TableCell class="font-medium text-foreground">{{ row.size }}</TableCell>
                                            <TableCell>{{ row.curve ? row.curve : '-' }}</TableCell>
                                            <TableCell>{{ Math.max(0, Number(row.units || 0)) }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <template v-if="!isLaundryMode">
                            <div class="mt-4 flex items-center gap-1.5">
                                <p class="text-sm font-semibold text-foreground">Procesos</p>
                                <HelpTooltip
                                    :text="`Describe procesos de ${displayNameLower} (spot color, alto relieve, ubicación, tintas).`"
                                    aria-label="Ayuda sobre Procesos"
                                />
                            </div>
                            <Textarea
                                :model-value="meta.processes"
                                rows="4"
                                placeholder="Ej: Spot color / Alto relieve / Ubicación..."
                                @update:model-value="(value) => updateProcesses(String(value))"
                            />
                            <p class="mt-1 text-right text-xs text-muted-foreground">{{ processesLength }}/{{ PROCESS_CHAR_LIMIT }}</p>
                        </template>

                        <div class="mt-4 flex items-center gap-1.5">
                            <p class="text-sm font-semibold text-foreground">Observación</p>
                            <HelpTooltip text="Describe observaciones adicionales, tintas, consideraciones." aria-label="Ayuda sobre Observación" />
                        </div>
                        <Textarea
                            :model-value="observationValue"
                            rows="5"
                            placeholder="Indicaciones adicionales, tintas, consideraciones..."
                            @update:model-value="(value) => updateObservation(String(value))"
                        />
                        <p class="mt-1 text-right text-xs text-muted-foreground">{{ observationLength }}/{{ OBSERVATION_CHAR_LIMIT }}</p>
                    </div>

                    <div class="space-y-4 pr-1">
                        <div class="rounded-lg border border-border bg-background p-4">
                            <div class="mb-3 flex items-center gap-1.5">
                                <p class="text-sm font-semibold text-foreground">Imagen de {{ displayNameLower }}</p>
                                <HelpTooltip
                                    :text="`Carga 1 imagen de ${displayNameLower} (JPG, PNG o WEBP; máximo 2MB).`"
                                    aria-label="Ayuda sobre Imagen"
                                />
                            </div>

                            <input ref="fileInputRef" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileSelected" />

                            <div
                                :class="[
                                    'group relative flex h-64 w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-muted/20 transition-all duration-200',
                                    processingImage ? 'cursor-wait opacity-70' : 'cursor-pointer hover:border-primary hover:shadow-lg',
                                ]"
                                @click="!processingImage && openFilePicker()"
                            >
                                <template v-if="imageAttachment">
                                    <div class="relative h-64 w-full">
                                        <img
                                            :src="imageAttachment.url"
                                            :alt="`Previsualización ${displayNameLower}`"
                                            class="absolute inset-0 h-full w-full object-contain transition-transform duration-200 group-hover:scale-105"
                                        />
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 z-20 bg-black/50 py-1 text-center text-xs text-primary-foreground">
                                        Cambiar imagen
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="z-10 flex h-full w-full flex-col items-center justify-center">
                                        <ImageIcon class="mb-2 h-12 w-12 text-muted-foreground" />
                                        <span class="text-sm text-muted-foreground">Haz clic para subir imagen</span>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <Button type="button" variant="outline" class="rounded-full" :disabled="processingImage" @click="openFilePicker">
                                    <Upload class="mr-2 h-4 w-4" />
                                    {{ imageAttachment ? 'Cambiar imagen' : 'Subir imagen' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="rounded-full text-destructive hover:text-destructive"
                                    :disabled="!imageAttachment"
                                    @click="removeImage"
                                >
                                    <X class="mr-2 h-4 w-4" />
                                    Eliminar
                                </Button>
                            </div>

                            <p v-if="imageError" class="mt-2 text-xs text-destructive">{{ imageError }}</p>

                            <div class="mt-4 border-t border-border pt-4">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-foreground">Dibujo plano</p>
                                    <p class="text-xs text-muted-foreground">Referencia visual del producto</p>
                                </div>

                                <button
                                    v-if="resolvedDrawingUrl"
                                    type="button"
                                    class="w-full overflow-hidden rounded-md bg-muted/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                                    aria-label="Abrir dibujo plano en tamaño grande"
                                    @click="openDrawingPreview"
                                >
                                    <img :src="resolvedDrawingUrl" alt="Dibujo plano de referencia" class="h-52 w-full object-cover" />
                                </button>

                                <div v-else class="flex h-52 items-center justify-center rounded-md bg-muted/20 px-3 text-sm text-muted-foreground">
                                    Sin dibujo plano
                                </div>
                            </div>

                            <div class="mt-4 border-t border-border pt-4">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-foreground">Colores - códigos</p>
                                    <p class="text-xs text-muted-foreground">Referencia visual</p>
                                </div>
                                <div v-if="colorSlots.length > 0" class="grid grid-cols-2 gap-2">
                                    <div
                                        v-for="slot in colorSlots"
                                        :key="slot.label"
                                        class="flex items-center gap-2 rounded-md border border-border bg-background px-2 py-2"
                                    >
                                        <div
                                            class="h-10 w-10 shrink-0 rounded-md border border-border bg-muted"
                                            :style="slot.hex ? { backgroundColor: slot.hex } : undefined"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-xs font-medium text-muted-foreground">{{ slot.label }}</p>
                                            <p class="truncate text-sm font-medium text-foreground">{{ slot.name }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2 rounded-md border border-border bg-background px-2 py-2">
                                    <div class="h-10 w-10 shrink-0 rounded-md border border-border bg-muted" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-muted-foreground">Color 1</p>
                                        <p class="text-sm font-medium text-foreground">Sin registro</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="handleClose">Cerrar</Button>
                <Button type="button" class="rounded-full px-6" :disabled="savingSection === effectiveSaveSection" @click="handleSave">
                    {{ savingSection === effectiveSaveSection ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <Dialog v-model:open="isDrawingPreviewOpen">
        <FormDialogContent class="max-w-4xl">
            <FormHeader title="Dibujo plano" description="Referencia visual del producto" />
            <div class="mt-2 flex max-h-[80vh] items-center justify-center overflow-hidden rounded-md border border-border bg-background p-3">
                <img
                    v-if="resolvedDrawingUrl"
                    :src="resolvedDrawingUrl"
                    alt="Dibujo plano de referencia en vista ampliada"
                    class="max-h-[75vh] w-full object-contain"
                />
            </div>
            <DialogFooter class="mt-4 justify-end">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="isDrawingPreviewOpen = false">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
