<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';
import DialogDescription from './ui/dialog/DialogDescription.vue';
import { SquareArrowOutUpRight } from 'lucide-vue-next';

interface Props {
    sizeGroups: Record<string, string[]>;
    modelValue: string | undefined;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);
const isDialogOpen = ref(false);
const localModelValue = ref<Record<string, boolean>>({}); // Renombrar para evitar conflicto

watch(
    () => props.modelValue,
    (newValue) => {
        try {
            localModelValue.value = typeof newValue === 'string'
                ? JSON.parse(newValue) // Deserializar si es una cadena JSON
                : {}; // Inicializar como un objeto vacío si no es una cadena
        } catch (error) {
            console.error('Error al analizar modelValue:', error);
            localModelValue.value = {}; // Restablecer a un objeto vacío en caso de error
        }
    },
    { immediate: true }
);

function updateModelValue(key: string, value: boolean) {
    const updatedValue = { ...localModelValue.value }; // Crear una copia mutable
    updatedValue[key] = value; // Actualizar la copia
    localModelValue.value = updatedValue; // Asignar la copia al estado reactivo
    emit('update:modelValue', JSON.stringify(localModelValue.value)); // Emitir como cadena JSON
}

function toggleGroup(group: string, value: boolean) {
    const updatedValue = { ...localModelValue.value }; // Crear una copia mutable
    props.sizeGroups[group].forEach((size) => {
        updatedValue[size] = value; // Actualizar la copia
    });
    localModelValue.value = updatedValue; // Asignar la copia al estado reactivo
    emit('update:modelValue', JSON.stringify(localModelValue.value)); // Emitir como cadena JSON
}

function isGroupChecked(group: string): boolean {
    return props.sizeGroups[group].every((size) => localModelValue.value[size]);
}

function resetLocalModelValue() {
    try {
        localModelValue.value = typeof props.modelValue === 'string'
            ? JSON.parse(props.modelValue) // Reiniciar al valor inicial del padre
            : {}; // Inicializar como un objeto vacío si no es una cadena
    } catch (error) {
        console.error('Error al reiniciar localModelValue:', error);
        localModelValue.value = {}; // Restablecer a un objeto vacío en caso de error
    }
}
</script>

<template>
    <div>
        <div class="mb-2">
            <Label class="font-medium">Tallas<span class="text-red-500">*</span></Label>
        </div>
        <Button class="w-full text-start" variant="outline" size="md" @click="isDialogOpen = true">Seleccionar Tallas <SquareArrowOutUpRight/></Button>
        <Dialog v-model:open="isDialogOpen" @close="resetLocalModelValue">
            <DialogContent class="max-h-[90vh] flex flex-col text-sm sm:max-w-2xl">
                <DialogHeader class="flex-shrink-0">
                    <DialogTitle>Seleccionar Tallas</DialogTitle>
                    <DialogDescription>
                        Selecciona las tallas disponibles para este producto. Puedes marcar todas las tallas de un grupo o elegirlas individualmente.
                    </DialogDescription>
                </DialogHeader>
                <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                    <div class="space-y-4">
                    <div v-for="(sizes, group) in sizeGroups" :key="group" class="rounde py-2">
                        <!-- Checkbox para seleccionar/deseleccionar todo el grupo -->
                        <label class="flex items-center font-medium text-gray-700">
                            <input
                                type="checkbox"
                                :checked="isGroupChecked(group)"
                                class="mr-1 h-3 w-3 rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-300"
                                @change="toggleGroup(group, ($event.target as HTMLInputElement).checked)"
                            />
                            (Seleccionar Todo)
                        </label>
                        <!-- Lista de checkboxes para cada talla individual -->
                        <div class="mt-1 flex flex-wrap gap-2">
                            <label
                                v-for="size in sizes"
                                :key="size"
                                class="flex cursor-pointer items-center rounded border bg-white px-2 py-1 shadow-sm hover:bg-gray-100"
                                style="flex: 0 0 auto;"
                            >
                                <input
                                    type="checkbox"
                                    :checked="localModelValue[size] ?? false"
                                    class="mr-1 h-3 w-3 rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-300"
                                    @change="updateModelValue(size, ($event.target as HTMLInputElement).checked)"
                                />
                                {{ size }}
                            </label>
                        </div>
                    </div>
                </div>
                </div>
                <DialogFooter class="flex justify-end">
                    <Button variant="default" size="sm" @click="isDialogOpen = false">Cerrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.summary-active {
    background-color: #f3f4f6;
}
</style>
