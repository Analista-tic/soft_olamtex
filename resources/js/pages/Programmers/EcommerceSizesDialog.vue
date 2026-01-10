<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import { computed, ref } from 'vue';

// Props recibidos desde el componente padre
const props = defineProps<{
    productSheet: { sizes: string | Record<string, boolean> | null; reference: string };
}>();

// Eventos emitidos hacia el componente padre
const emit = defineEmits(['sizesUpdated']);

// Estado reactivo
const isDialogOpen = ref(false);
const ecommerceSizes = ref<Record<string, number>>({});
const editableReference = ref(props.productSheet.reference);

// Convierte las tallas de JSON a un objeto
const parsedSizes = computed(() => {
    try {
        // Si sizes ya es un objeto, usarlo directamente
        const sizesData = typeof props.productSheet.sizes === 'string' 
            ? JSON.parse(props.productSheet.sizes || '{}')
            : props.productSheet.sizes || {};
        
        const sizes = sizesData.sizes || sizesData;

        // Diferenciar entre creación (valores booleanos) y edición (valores numéricos)
        return Object.fromEntries(
            Object.entries(sizes)
                .filter(([, value]) => typeof value === 'boolean' ? value : true) // Filtrar solo claves con `true` en creación
                .map(([key, value]) => [key, typeof value === 'number' ? value : 0]) // Mantener valores numéricos o inicializar en 0
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
            acc[size] = ecommerceSizes.value[size] ?? value; // Mantener valores existentes o inicializar en 0
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
        Object.entries(ecommerceSizes.value).map(([size, value]) => [size, value || 0]) // Asegura que las cantidades sean al menos 0
    );

    emit('sizesUpdated', { sizes: sanitizedSizes, reference: editableReference.value });
    isDialogOpen.value = false;
}
</script>

<template>
    <Dialog
        v-model:open="isDialogOpen"
        @update:open="handleDialogOpen"
    >
        <DialogTrigger as-child>
            <Button variant="default" size="sm" class="rounded-full">E-commerce</Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-4xl max-h-[90vh] flex flex-col p-0 gap-0">
            <div class="flex-shrink-0 px-6 pt-6 pb-4 border-b">
                <h2 class="text-xl font-semibold">Asignar Cantidades E-commerce</h2>
                <p class="text-sm text-muted-foreground mt-1">Aquí puedes asignar las cantidades de tallas para el producto seleccionado.</p>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-4 scroll-smooth" style="scrollbar-width: thin;">
                <!-- Campo para editar la referencia -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">Referencia E-commerce</label>
                    <InputField 
                        label="" 
                        v-model="editableReference" 
                        placeholder="Ingrese la referencia" 
                    />
                </div>

                <!-- Grid de tallas habilitadas -->
                <div v-if="filteredSizes.length > 0">
                    <h3 class="text-sm font-medium mb-3">Cantidades por Talla</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div 
                            v-for="([size], index) in filteredSizes" 
                            :key="`size-${index}`"
                            class="flex flex-col gap-2 p-4 border rounded-lg bg-muted/30 hover:bg-muted/50 transition-colors"
                        >
                            <label class="text-sm font-medium text-center">Talla {{ size }}</label>
                            <InputField 
                                label="" 
                                type="number" 
                                v-model="ecommerceSizes[size]" 
                                placeholder="0"
                                min="0"
                                class="text-center"
                            />
                        </div>
                    </div>
                </div>

                <!-- Mensaje si no hay tallas habilitadas -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="w-16 h-16 text-muted-foreground/50 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-muted-foreground font-medium">No hay tallas habilitadas</p>
                    <p class="text-sm text-muted-foreground mt-1">Este producto no tiene tallas configuradas</p>
                </div>
            </div>

            <div class="flex-shrink-0 border-t px-6 py-4 flex items-center justify-end gap-3 bg-white">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-6 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="isDialogOpen = false"
                >
                    Cancelar
                </button>
                <Button 
                    type="button"
                    class="flex items-center justify-center rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm"
                    @click="saveSizes"
                >
                    Guardar
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
