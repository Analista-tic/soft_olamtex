<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import { computed, ref } from 'vue';

// Props recibidos desde el componente padre
const props = defineProps<{
    productSheet: { sizes: string; reference: string };
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
        const sizesData = JSON.parse(props.productSheet.sizes || '{}');
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
            <Button variant="default" size="sm">E-commerce</Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-6xl max-h-[90vh] flex flex-col">
            <FormHeader
                class="flex-shrink-0"
                :title="'Asignar Cantidades E-commerce'"
                description="Aquí puedes asignar las cantidades de tallas para el producto seleccionado."
            />

            <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div>
                <!-- Campo para editar la referencia -->
                <div class="mb-4 flex items-center">
                    <span class="mr-2 font-medium text-nowrap">Referencia E-commerce:</span>
                    <InputField label="" v-model="editableReference" placeholder="Editar referencia" />
                </div>

                <!-- Tabla de tallas habilitadas -->
                <div v-if="filteredSizes.length > 0" class="overflow-x-auto">
                    <Table class="w-full rounded-md border border-gray-300 shadow-md dark:border-gray-700">
                        <TableHeader>
                            <TableRow>
                                <TableCell v-for="([size], index) in filteredSizes" :key="`header-${index}`" class="font-medium">
                                    Talla - {{ size }}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell v-for="([size], index) in filteredSizes" :key="`input-${index}`" class="py-2">
                                    <InputField label="" type="number" v-model="ecommerceSizes[size]" placeholder="Cantidad" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Mensaje si no hay tallas habilitadas -->
                <div v-else class="text-center text-gray-500">No hay tallas habilitadas para este producto.</div>
            </div>
            </div>

            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4">
                <Button variant="default" size="sm" @click="saveSizes">Guardar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
