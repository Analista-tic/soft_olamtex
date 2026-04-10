<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import unitMeasureData from '@/data/unitMeasures.json';
import type { SupplyRequestPayload, SupplyRequestResult, SupplySubGroup } from '@/domain/Production';
import { fetchSettingsParameters, fetchSupplySubGroups, requestSupplyItem } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { FileText, ImageIcon, Loader2, Send, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/* ── Props & Emits ───────────────────────────────────────────── */

const props = defineProps<{
    isOpen: boolean;
    opId: string | number;
    prefillName?: string;
    opCode?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', result: SupplyRequestResult): void;
}>();

type UnitMeasureGroup = { label: string; options: Option[] };
type UnitMeasureData = { unitMeasureGroups: UnitMeasureGroup[] };

const fallbackUnitMeasureGroups: UnitMeasureGroup[] = (unitMeasureData as UnitMeasureData)?.unitMeasureGroups ?? [];
const fallbackUnitMeasures: Option[] = fallbackUnitMeasureGroups.flatMap((group) =>
    group.options.map((option) => ({
        id: String(option.id),
        name: String(option.name ?? option.id),
        groupLabel: group.label,
    })),
);

const DEFAULT_UNIT_MEASURE = String(fallbackUnitMeasures[0]?.id ?? 'unidad');

/* ── State ───────────────────────────────────────────────────── */

const dialogOpen = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');

const subGroups = ref<SupplySubGroup[]>([]);
const isLoadingSubGroups = ref(false);
const unitMeasureOptions = ref<Option[]>([...fallbackUnitMeasures]);
const isLoadingUnitMeasures = ref(false);
const isHydrating = ref(false);

/* Formulario con campos de orden de compra */
const form = ref({
    name: '',
    description: '',
    category_sub_group_id: null as number | null,
    unit_measure: DEFAULT_UNIT_MEASURE,
    quantity: 1,
    unit_price: 0,
    provider_name: '',
    observation: '',
});

/* Imagen de referencia */
const referenceFile = ref<File | null>(null);
const referencePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

/* ── Computed ────────────────────────────────────────────────── */

const subGroupOptions = computed<Option[]>(() => subGroups.value.map((sg) => ({ id: sg.id, name: sg.name })));

const isBootstrapping = computed(() => dialogOpen.value && (isLoadingSubGroups.value || isLoadingUnitMeasures.value));
const canRenderForm = computed(() => !isBootstrapping.value && !isHydrating.value);

const canSubmit = computed(() => {
    if (!canRenderForm.value) return false;
    if (isSubmitting.value) return false;
    if (!form.value.name.trim()) return false;
    if (!String(form.value.unit_measure ?? '').trim()) return false;
    if (form.value.quantity <= 0) return false;
    return true;
});

const estimatedTotal = computed(() => {
    return Number(form.value.quantity || 0) * Number(form.value.unit_price || 0);
});

const isImageFile = computed(() => {
    if (referenceFile.value) {
        return referenceFile.value.type.startsWith('image/');
    }
    return false;
});

/* ── Dialog lifecycle ────────────────────────────────────────── */

watch(
    () => props.isOpen,
    async (open) => {
        dialogOpen.value = open;
        if (open) {
            resetForm();
            form.value.name = props.prefillName?.trim() ?? '';
            await Promise.all([loadSubGroups(), loadUnitMeasures()]);
        }
    },
);

function handleClose(): void {
    dialogOpen.value = false;
    emit('close');
}

function resetForm(): void {
    errorMessage.value = '';
    form.value = {
        name: '',
        description: '',
        category_sub_group_id: null,
        unit_measure: DEFAULT_UNIT_MEASURE,
        quantity: 1,
        unit_price: 0,
        provider_name: '',
        observation: '',
    };
    referenceFile.value = null;
    referencePreview.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

/* ── Data ────────────────────────────────────────────────────── */

async function loadSubGroups(): Promise<void> {
    if (subGroups.value.length > 0) return;
    isLoadingSubGroups.value = true;
    try {
        subGroups.value = await fetchSupplySubGroups();
    } catch {
        console.error('Error al cargar subgrupos de insumos');
    } finally {
        isLoadingSubGroups.value = false;
    }
}

function normalizeUnitMeasureOptions(options: Option[]): Option[] {
    const unique = new Map<string, Option>();

    options.forEach((option) => {
        const id = String(option?.id ?? '').trim();
        const name = String(option?.name ?? '').trim();

        if (!id || !name) {
            return;
        }

        if (!unique.has(id)) {
            unique.set(id, {
                id,
                name,
            });
        }
    });

    return Array.from(unique.values());
}

async function loadUnitMeasures(): Promise<void> {
    isLoadingUnitMeasures.value = true;

    try {
        const parameters = await fetchSettingsParameters(['unit_measures']);
        const options = normalizeUnitMeasureOptions(parameters.unit_measures ?? []);
        unitMeasureOptions.value = options.length > 0 ? options : [...fallbackUnitMeasures];
    } catch (err) {
        console.error('Error al cargar unidades de medida:', err);
        unitMeasureOptions.value = [...fallbackUnitMeasures];
    } finally {
        const currentUnit = String(form.value.unit_measure ?? '').trim();
        const hasCurrentUnit = unitMeasureOptions.value.some((option) => String(option.id) === currentUnit);

        if (!currentUnit || !hasCurrentUnit) {
            form.value.unit_measure = String(unitMeasureOptions.value[0]?.id ?? DEFAULT_UNIT_MEASURE);
        }
        isLoadingUnitMeasures.value = false;
    }
}

/* ── Imagen de referencia ────────────────────────────────────── */

function triggerFileInput(): void {
    fileInputRef.value?.click();
}

function handleFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        errorMessage.value = 'Solo se permiten imágenes (JPEG, PNG, WebP) y PDF.';
        errorType.value = 'warning';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        errorMessage.value = 'El archivo es demasiado grande. Máximo 10MB.';
        errorType.value = 'warning';
        return;
    }

    referenceFile.value = file;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            referencePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        referencePreview.value = null;
    }
}

function removeFile(): void {
    referenceFile.value = null;
    referencePreview.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

/* ── Submit ──────────────────────────────────────────────────── */

async function handleSubmit(): Promise<void> {
    if (!canSubmit.value) return;
    errorMessage.value = '';
    isSubmitting.value = true;

    try {
        const payload: SupplyRequestPayload = {
            name: form.value.name.trim(),
            description: form.value.description?.trim() || '',
            category_sub_group_id: form.value.category_sub_group_id,
            unit_measure: form.value.unit_measure?.trim() || DEFAULT_UNIT_MEASURE,
            quantity: Number(form.value.quantity) || 1,
            unit_price: Number(form.value.unit_price) || 0,
            provider_name: form.value.provider_name?.trim() || null,
            observation: form.value.observation?.trim() || '',
        };

        const result = await requestSupplyItem(props.opId, payload, referenceFile.value);
        emit('saved', result);
        handleClose();
    } catch (err: any) {
        const message = err?.response?.data?.message ?? err?.response?.data?.error ?? 'Error al crear la solicitud de insumo.';
        errorMessage.value = message;
        errorType.value = 'error';
    } finally {
        isSubmitting.value = false;
    }
}

function onSubGroupChange(value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    if (value === null || value === undefined || value === '') {
        form.value.category_sub_group_id = null;
        return;
    }
    form.value.category_sub_group_id = Number(value) || null;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value);
}
</script>

<template>
    <Dialog
        :open="dialogOpen"
        @update:open="
            (value: boolean) => {
                if (!value) handleClose();
            }
        "
    >
        <FormDialogContent class="flex max-h-[95vh] max-w-[1100px] flex-col px-2 py-2" :scrollable="false">
            <FormHeader
                title="Solicitar insumo"
                description="Registra la información de compra del insumo, adjunta una referencia visual y crea la solicitud para gestión en compras."
            >
                <div v-if="opCode" class="pt-1 text-xs font-medium text-primary">OP {{ opCode }}</div>
            </FormHeader>

            <AlertStack :error-message="errorMessage" :error-type="errorType" @close-error="errorMessage = ''" />

            <div class="relative min-h-0 flex-1 overflow-y-auto px-1 pr-1 pt-4" :class="{ 'min-h-[420px]': !canRenderForm }">
                <FormLoadingOverlay
                    :active="!canRenderForm"
                    :delay-ms="0"
                    message="Cargando solicitud de insumo..."
                    description="Preparando categorías y unidades de medida necesarias."
                />

                <form v-if="canRenderForm" class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_320px]" @submit.prevent="handleSubmit">
                    <div class="space-y-6">
                        <section class="rounded-xl border border-border bg-card p-4">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-foreground">Identificación del insumo</h3>
                                <p class="text-xs text-muted-foreground">Define el artículo solicitado y clasifícalo dentro del catálogo.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="grid gap-1.5 md:col-span-2">
                                    <Label for="supply-name">Nombre del insumo *</Label>
                                    <Input
                                        id="supply-name"
                                        v-model="form.name"
                                        placeholder="Ej: Botón metálico 18 mm color níquel"
                                        :disabled="isSubmitting"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label>Categoría</Label>
                                    <SelectField
                                        :model-value="form.category_sub_group_id"
                                        :options="subGroupOptions"
                                        :searchable="true"
                                        :disabled="isSubmitting || isLoadingSubGroups"
                                        placeholder="Seleccionar categoría..."
                                        search-placeholder="Buscar categoría..."
                                        @update:model-value="onSubGroupChange"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="supply-provider">Proveedor sugerido</Label>
                                    <Input
                                        id="supply-provider"
                                        v-model="form.provider_name"
                                        placeholder="Nombre del proveedor o referencia comercial"
                                        :disabled="isSubmitting"
                                    />
                                </div>

                                <div class="grid gap-1.5 md:col-span-2">
                                    <Label for="supply-description">Descripción técnica</Label>
                                    <Textarea
                                        id="supply-description"
                                        v-model="form.description"
                                        placeholder="Detalle material, color, acabado, medidas, composición o especificaciones del insumo..."
                                        :disabled="isSubmitting"
                                        rows="3"
                                    />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-border bg-card p-4">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-foreground">Condiciones de compra</h3>
                                <p class="text-xs text-muted-foreground">Completa los datos necesarios para que compras gestione la solicitud.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-4">
                                <div class="grid gap-1.5">
                                    <Label>Unidad de medida *</Label>
                                    <SelectField
                                        v-model="form.unit_measure"
                                        :options="unitMeasureOptions"
                                        :searchable="true"
                                        :disabled="isSubmitting || isLoadingUnitMeasures"
                                        placeholder="Seleccionar unidad..."
                                        search-placeholder="Buscar unidad..."
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="supply-qty">Cantidad *</Label>
                                    <Input
                                        id="supply-qty"
                                        :model-value="String(form.quantity)"
                                        type="number"
                                        min="1"
                                        step="1"
                                        :disabled="isSubmitting"
                                        @update:model-value="
                                            (v: string | number) => {
                                                form.quantity = Math.max(1, Number(v) || 1);
                                            }
                                        "
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="supply-price">Precio unitario estimado</Label>
                                    <Input
                                        id="supply-price"
                                        :model-value="String(form.unit_price)"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="isSubmitting"
                                        @update:model-value="
                                            (v: string | number) => {
                                                form.unit_price = Math.max(0, Number(v) || 0);
                                            }
                                        "
                                    />
                                </div>

                                <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
                                    <p class="text-xs text-muted-foreground">Monto estimado</p>
                                    <p class="text-sm font-semibold tabular-nums">{{ formatCurrency(estimatedTotal) }}</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-border bg-card p-4">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-foreground">Observaciones</h3>
                                <p class="text-xs text-muted-foreground">Agrega instrucciones, notas para logística o cualquier aclaración útil.</p>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="supply-obs">Observaciones para logística</Label>
                                <Textarea
                                    id="supply-obs"
                                    v-model="form.observation"
                                    placeholder="Ej: validar tono, confirmar existencia con proveedor, entregar muestra física, etc."
                                    :disabled="isSubmitting"
                                    rows="3"
                                />
                            </div>
                        </section>
                    </div>

                    <aside class="self-start lg:sticky lg:top-0">
                        <div class="space-y-4 rounded-xl border border-border bg-card p-4">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Referencia visual</h3>
                                <p class="text-xs text-muted-foreground">Adjunta imagen o PDF para facilitar la compra del insumo.</p>
                            </div>

                            <input
                                ref="fileInputRef"
                                type="file"
                                class="hidden"
                                accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf"
                                @change="handleFileSelect"
                            />

                            <div
                                v-if="!referenceFile"
                                class="flex min-h-[260px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-muted/30 p-4 text-center"
                            >
                                <ImageIcon class="mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="text-sm font-medium">Sin referencia adjunta</p>
                                <p class="mt-1 text-xs text-muted-foreground">JPEG, PNG, WebP o PDF. Tamaño máximo 10MB.</p>
                                <Button type="button" variant="outline" size="sm" class="mt-4" :disabled="isSubmitting" @click="triggerFileInput">
                                    <Upload class="mr-1.5 h-4 w-4" />
                                    Cargar archivo
                                </Button>
                            </div>

                            <div v-else class="space-y-3">
                                <div v-if="isImageFile && referencePreview" class="overflow-hidden rounded-xl">
                                    <ImageViewer
                                        :src="referencePreview"
                                        alt="Referencia visual"
                                        readonly
                                        @delete="removeFile"
                                        @update="triggerFileInput"
                                    />
                                </div>
                                <div v-else class="overflow-hidden rounded-xl border border-border bg-muted/20">
                                    <div class="flex h-[220px] items-center justify-center bg-muted/30">
                                        <div class="text-center">
                                            <FileText class="mx-auto h-10 w-10 text-muted-foreground" />
                                            <p class="mt-2 text-xs text-muted-foreground">Vista previa no disponible</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-border px-3 py-2">
                                    <p class="truncate text-sm font-medium">{{ referenceFile.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ (referenceFile.size / 1024).toFixed(1) }} KB</p>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" size="sm" :disabled="isSubmitting" @click="triggerFileInput">
                                        Cambiar
                                    </Button>
                                    <Button type="button" variant="ghost" size="icon" :disabled="isSubmitting" @click="removeFile">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Resumen</p>
                                <div class="mt-2 space-y-1 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-muted-foreground">Unidad</span>
                                        <span class="font-medium">{{ form.unit_measure || '—' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-muted-foreground">Cantidad</span>
                                        <span class="font-medium">{{ form.quantity }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-muted-foreground">Proveedor</span>
                                        <span class="max-w-[160px] truncate text-right font-medium">{{ form.provider_name || 'Pendiente' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-primary/10 pt-2">
                                        <span class="text-muted-foreground">Total estimado</span>
                                        <span class="font-semibold">{{ formatCurrency(estimatedTotal) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </form>
            </div>

            <DialogFooter class="mt-4 justify-end gap-2 border-t px-1 pt-4">
                <Button type="button" variant="outline" :disabled="isSubmitting" @click="handleClose"> Cancelar </Button>
                <Button type="button" :disabled="!canSubmit" @click="handleSubmit">
                    <Loader2 v-if="isSubmitting" class="mr-1.5 h-4 w-4 animate-spin" />
                    <Send v-else class="mr-1.5 h-4 w-4" />
                    {{ isSubmitting ? 'Creando...' : 'Crear solicitud' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
