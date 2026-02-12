<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { computed, ref } from 'vue';

// Props recibidos desde el componente padre
const props = withDefaults(defineProps<{
    productSheet: { sizes: string | Record<string, boolean> | null; reference: string };
    existingValues?: Record<string, number> | null;
    readonly?: boolean;
    triggerLabel?: string;
}>(), {
    existingValues: null,
    readonly: false,
    triggerLabel: '',
});

// Eventos emitidos hacia el componente padre
const emit = defineEmits(['sizesUpdated']);

const isReadOnly = computed(() => props.readonly === true);
const triggerText = computed(() => {
    if (props.triggerLabel && props.triggerLabel.trim() !== '') return props.triggerLabel;
    return isReadOnly.value ? 'Ver e-commerce' : 'E-commerce';
});

// Estado reactivo
const isDialogOpen = ref(false);
const ecommerceSizes = ref<Record<string, number>>({});
const editableReference = ref(props.productSheet.reference);

// Convierte las tallas de JSON a un objeto
const parsedSizes = computed(() => {
    try {
        let sizesData: Record<string, unknown> | unknown[] = {};
        if (typeof props.productSheet.sizes === 'string') {
            const raw = props.productSheet.sizes.trim();
            if (raw !== '') {
                try {
                    const parsed = JSON.parse(raw) as Record<string, unknown> | unknown[];
                    sizesData = parsed;
                } catch {
                    const csvSizes = raw
                        .split(',')
                        .map((size) => size.trim())
                        .filter((size) => size !== '');
                    sizesData = Object.fromEntries(csvSizes.map((size) => [size, true]));
                }
            }
        } else if (props.productSheet.sizes && typeof props.productSheet.sizes === 'object') {
            sizesData = props.productSheet.sizes;
        }

        const sizes =
            !Array.isArray(sizesData) &&
            sizesData &&
            typeof sizesData === 'object' &&
            'sizes' in sizesData
                ? ((sizesData as { sizes?: unknown }).sizes ?? sizesData)
                : sizesData;

        // Si es un array (formato normalizado: ["28", "30", "32"])
        if (Array.isArray(sizes)) {
            return Object.fromEntries(sizes.map((size) => [String(size), 0]));
        }

        // Si es un objeto (formato antiguo o con valores)
        return Object.fromEntries(
            Object.entries(sizes)
                .filter(([, value]) => (typeof value === 'boolean' ? value : true)) // Filtrar solo claves con `true` en creación
                .map(([key, value]) => [key, typeof value === 'number' ? value : 0]), // Mantener valores numéricos o inicializar en 0
        );
    } catch (error) {
        console.error('Error al parsear las tallas:', error);
        return {};
    }
});

// Filtra las tallas habilitadas (si es necesario)
const filteredSizes = computed(() => {
    return Object.entries(parsedSizes.value);
});

// Inicializa las cantidades de tallas al abrir el diálogo
function initializeSizes() {
    ecommerceSizes.value = Object.entries(parsedSizes.value).reduce(
        (acc, [size, value]) => {
            // Si hay valores existentes del lote, usarlos
            if (props.existingValues && typeof props.existingValues[size] === 'number') {
                acc[size] = props.existingValues[size];
            } else {
                // Si no, usar el valor de parsedSizes o 0
                acc[size] = typeof value === 'number' ? value : 0;
            }
            return acc;
        },
        {} as Record<string, number>,
    );
}

// Sincroniza datos al abrir/cerrar el diálogo
function handleDialogOpen(open: boolean) {
    if (open) {
        initializeSizes();
        editableReference.value = props.productSheet.reference;
    }
}

// Guarda las tallas y emite los datos actualizados
function saveSizes() {
    const sanitizedSizes = Object.fromEntries(
        Object.entries(ecommerceSizes.value).map(([size, value]) => {
            // Convertir valores null, undefined o vacíos a 0
            const numValue = typeof value === 'number' ? value : value ? Number(value) : 0;
            return [size, Math.max(0, numValue)]; // Asegurar que no sea negativo
        }),
    );

    emit('sizesUpdated', { sizes: sanitizedSizes, reference: editableReference.value });
    isDialogOpen.value = false;
}
</script>

<template>
    <Dialog v-model:open="isDialogOpen" @update:open="handleDialogOpen">
        <DialogTrigger as-child>
            <Button variant="default" size="sm" class="rounded-full">
                {{ triggerText }}
            </Button>
        </DialogTrigger>

        <FormDialogContent class="flex max-h-[95vh] max-w-[140vh] flex-col px-2 py-2">
            <div class="flex-shrink-0 border-b px-6 pb-4 pt-6">
                <h2 class="text-xl font-semibold">Asignar Cantidades E-commerce</h2>
                <p class="mt-1 text-sm text-muted-foreground">Aquí puedes asignar las cantidades de tallas para el producto seleccionado.</p>
            </div>

            <div class="flex-1 overflow-y-auto scroll-smooth px-6 py-4" style="scrollbar-width: thin">
                <!-- Campo para editar la referencia -->
                <div class="mb-6">
                    <label class="mb-2 block text-sm font-medium">Referencia E-commerce</label>
                    <InputField label="" v-model="editableReference" placeholder="Ingrese la referencia" :readonly="isReadOnly" />
                </div>

                <!-- Grid de tallas habilitadas -->
                <div v-if="filteredSizes.length > 0">
                    <h3 class="mb-3 text-sm font-medium">Cantidades por Talla</h3>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <div
                            v-for="([size], index) in filteredSizes"
                            :key="`size-${index}`"
                            class="flex flex-col gap-2 rounded-lg border bg-muted/30 p-4 transition-colors hover:bg-muted/50"
                        >
                            <label class="text-center text-sm font-medium">Talla {{ size }}</label>
                            <InputField
                                label=""
                                type="number"
                                v-model="ecommerceSizes[size]"
                                placeholder="0"
                                min="0"
                                step="1"
                                class="text-center"
                                :readonly="isReadOnly"
                                @blur="ecommerceSizes[size] = ecommerceSizes[size] ?? 0"
                            />
                        </div>
                    </div>
                </div>

                <!-- Mensaje si no hay tallas habilitadas -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="mb-4 h-16 w-16 text-muted-foreground/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                        />
                    </svg>
                    <p class="font-medium text-muted-foreground">No hay tallas habilitadas</p>
                    <p class="mt-1 text-sm text-muted-foreground">Este producto no tiene tallas configuradas</p>
                </div>
            </div>

            <div class="flex flex-shrink-0 items-center justify-end gap-3 border-t bg-white px-6 py-4">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-6 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="isDialogOpen = false"
                >
                    {{ isReadOnly ? 'Cerrar' : 'Cancelar' }}
                </button>
                <Button
                    v-if="!isReadOnly"
                    type="button"
                    class="flex items-center justify-center rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm"
                    @click="saveSizes"
                >
                    Guardar
                </Button>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
