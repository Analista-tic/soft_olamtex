<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { ChevronDown, ChevronRight, Pencil } from 'lucide-vue-next';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    anexo?: string | null;
    name: string;
    normalized_name: string;
    generated_description: string;
    section_id: number | null;
    measure: number | null;
    quantity: number | null;
    description: string | null;
    sam_base: number | null;
    factor_operario?: number | null;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    is_active: boolean;
    created_at: string;
    section?: { id: number; name: string; type?: string } | null;
    product_sheet_reference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    productSheetReference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    machine_reference?: { id: number; code: string; name: string } | null;
    machineReference?: { id: number; code: string; name: string } | null;
    created_by?: { id: number; name: string } | null;
};

const props = defineProps<{
    open: boolean;
    operation: OperationRecord | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'edit'): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (v) => {
        localOpen.value = v;
    },
);
watch(localOpen, (v) => {
    if (!v) emit('close');
});

type ReferenceSheet = {
    id: number;
    reference: string;
    version: number;
    flat_sketch_url: string | null;
};

const referencesExpanded = ref(false);
const referencesLoading = ref(false);
const referencesLoaded = ref(false);
const references = ref<ReferenceSheet[]>([]);
const lightboxUrl = ref<string | null>(null);
const lightboxOpen = computed({
    get: () => lightboxUrl.value !== null,
    set: (v) => { if (!v) lightboxUrl.value = null; },
});

watch(
    () => props.operation,
    () => {
        referencesExpanded.value = false;
        referencesLoaded.value = false;
        references.value = [];
    },
);

async function toggleReferences() {
    referencesExpanded.value = !referencesExpanded.value;
    if (referencesExpanded.value && !referencesLoaded.value && props.operation) {
        referencesLoading.value = true;
        try {
            const { data } = await axios.get(`/produccion/operaciones/${props.operation.id}/referencias-activas`);
            references.value = data.data;
            referencesLoaded.value = true;
        } finally {
            referencesLoading.value = false;
        }
    }
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle class="flex items-center gap-2">
                    Detalle de operación
                    <TableStatusBadge
                        v-if="operation"
                        :label="operation.is_active ? 'Activa' : 'Inactiva'"
                        :tone="operation.is_active ? 'green' : 'red'"
                    />
                </DialogTitle>
                <DialogDescription v-if="operation">
                    {{ operation.name }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div v-if="operation" class="space-y-4">
                    <!-- Nombre principal -->
                    <div class="rounded-md border border-primary/20 bg-primary/5 px-4 py-3 text-center">
                        <p class="font-mono text-lg font-bold text-primary">{{ operation.normalized_name }}</p>
                        <p v-if="operation.codigo" class="mt-1 text-xs text-muted-foreground">{{ operation.codigo }}</p>
                    </div>

                    <!-- Filas con separadores -->
                    <div class="divide-y divide-border/60 text-sm">

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Sección</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.section?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Tipo</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.section?.type ?? '-' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Acción</p>
                                <p class="mt-0.5 font-medium uppercase text-foreground">{{ operation.action }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Parte de prenda</p>
                                <p class="mt-0.5 font-medium uppercase text-foreground">{{ operation.garment_part }}</p>
                            </div>
                        </div>

                        <div v-if="operation.anexo" class="py-3">
                            <p class="text-xs font-medium text-muted-foreground">Complemento (anexo)</p>
                            <p class="mt-0.5 font-medium uppercase text-foreground">{{ operation.anexo }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Medida</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.measure != null ? `${operation.measure} cm` : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Cantidad</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.quantity ?? '-' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">SAM base</p>
                                <p class="mt-0.5 font-mono font-bold text-primary">
                                    {{ operation.sam_base != null ? `${operation.sam_base.toFixed(2)} min` : '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Factor operario</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.factor_operario != null ? `${operation.factor_operario}%` : '-' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Referencia (Sábana de producto)</p>
                                <p class="mt-0.5 font-medium text-foreground">
                                    {{
                                        (operation.productSheetReference?.sample_reference ??
                                            operation.productSheetReference?.product_reference ??
                                            operation.product_sheet_reference?.sample_reference ??
                                            operation.product_sheet_reference?.product_reference) ||
                                        '-'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Máquina de referencia</p>
                                <p class="mt-0.5 font-medium text-foreground">
                                    {{
                                        (operation.machineReference ?? operation.machine_reference)
                                            ? `${(operation.machineReference ?? operation.machine_reference)!.code} · ${(operation.machineReference ?? operation.machine_reference)!.name}`
                                            : '-'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="py-3">
                            <p class="text-xs font-medium text-muted-foreground">Descripción</p>
                            <p class="mt-0.5 font-medium text-foreground">{{ (operation.generated_description || '-').toUpperCase() }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 py-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Creado por</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.created_by?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Fecha de creación</p>
                                <p class="mt-0.5 font-medium text-foreground">{{ operation.created_at ? new Date(operation.created_at).toLocaleDateString('es-CO') : '-' }}</p>
                            </div>
                        </div>

                    </div>

                    <!-- Referencias desplegables -->
                    <div class="pt-1">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md border border-border px-4 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted/40"
                            @click="toggleReferences"
                        >
                            <component :is="referencesExpanded ? ChevronDown : ChevronRight" class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span>Ver referencias donde se usa</span>
                            <span
                                v-if="referencesLoaded"
                                class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary"
                            >
                                {{ references.length }}
                            </span>
                        </button>

                        <div v-if="referencesExpanded" class="mt-3">
                            <div v-if="referencesLoading" class="py-6 text-center text-sm text-muted-foreground">
                                Cargando...
                            </div>
                            <div v-else-if="referencesLoaded && references.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                                Esta operación no está en ninguna sábana activa.
                            </div>
                            <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                <div
                                    v-for="sheet in references"
                                    :key="sheet.id"
                                    class="overflow-hidden rounded-md border border-border"
                                >
                                    <div
                                        class="flex h-24 items-center justify-center overflow-hidden bg-muted/30"
                                        :class="sheet.flat_sketch_url ? 'cursor-zoom-in' : ''"
                                        @click="sheet.flat_sketch_url && (lightboxUrl = sheet.flat_sketch_url)"
                                    >
                                        <img
                                            v-if="sheet.flat_sketch_url"
                                            :src="sheet.flat_sketch_url"
                                            :alt="sheet.reference"
                                            class="h-full w-full object-contain"
                                            @error="(e) => { (e.target as HTMLImageElement).style.display = 'none'; (e.target as HTMLImageElement).nextElementSibling?.classList.remove('hidden'); }"
                                        />
                                        <span :class="sheet.flat_sketch_url ? 'hidden' : ''" class="text-xs text-muted-foreground">Sin boceto</span>
                                    </div>
                                    <div class="px-3 py-2">
                                        <p class="text-sm font-semibold text-foreground">{{ sheet.reference }}</p>
                                        <p class="text-xs text-muted-foreground">V{{ sheet.version }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="emit('edit')"> <Pencil class="mr-1 h-4 w-4" /> Editar </Button>
                <Button variant="outline" size="pill" @click="localOpen = false">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Lightbox -->
    <Dialog v-model:open="lightboxOpen">
        <DialogContent class="max-w-xl">
            <div class="flex items-center justify-center py-2">
                <img
                    v-if="lightboxUrl"
                    :src="lightboxUrl"
                    class="max-h-[70vh] w-auto object-contain"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>
