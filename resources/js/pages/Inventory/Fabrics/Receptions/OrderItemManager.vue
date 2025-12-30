<script setup lang="ts">
import { ref, watch } from 'vue';
import type { FabricOrderItem, FabricRoll } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';

const props = defineProps<{
    items: FabricOrderItem[];
}>();


const items = ref(props.items || []); // Asegúrate de que sea un array por defecto

watch(
    () => props.items,
    (newItems) => {
        items.value = newItems || []; // Asegúrate de que siempre sea un array
    },
    { immediate: true }
);

const receptionDialogOpen = ref(false);
const receptionItem = ref<FabricOrderItem | null>(null);

const fabricRoll = ref<FabricRoll>({
    location: '',
    fabric_color_id: null,
    fabric_order_item_id: 0,
    received_quantity: 0,
    width: 0,
    quantity: 0,
});

function openReceptionDialog(item: FabricOrderItem) {
    receptionItem.value = item;
    fabricRoll.value = {
        location: '',
        fabric_color_id: null,
        fabric_order_item_id: item.id as number, // Asignar el ID del ítem
        received_quantity: 0,
        width: 0,
        quantity: 0,
    };
    receptionDialogOpen.value = true;
}

function closeReceptionDialog() {
    receptionItem.value = null;
    receptionDialogOpen.value = false;
}

function registerModel() {
    if (receptionItem.value && fabricRoll.value) {
        // Lógica para registrar el nuevo modelo
        console.log('Registrando modelo:', fabricRoll.value);
        closeReceptionDialog();
    }
}

</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-md">Telas</h3>
        </div>
        <div v-if="items?.length > 0"> <!-- Verifica que items sea válido antes de acceder a length -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-0">Tela</TableHead>
                        <TableHead class="pl-0">Base de Tela</TableHead>
                        <TableHead class="pl-0">Color del Proveedor</TableHead>
                        <TableHead class="pl-0">Código de Color</TableHead>
                        <TableHead class="pl-0">Cantidad</TableHead>
                        <TableHead class="pl-0">Ancho Estimado</TableHead>
                        <TableHead class="pl-0">Precio</TableHead>
                        <TableHead class="pl-0 text-center">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(item, index) in items" :key="index">
                        <TableCell class="pl-0">{{ item.fabric }}</TableCell>
                        <TableCell class="pl-0">
                            {{
                                item.base_fabric?.name || 'N/A'
                            }}
                        </TableCell>
                        <TableCell class="pl-0">{{ item.provider_color }}</TableCell>
                        <TableCell class="pl-0">{{ item.provider_color_code }}</TableCell>
                        <TableCell class="pl-0">{{ item.quantity }}</TableCell>
                        <TableCell class="pl-0">{{ item.estimated_width }}</TableCell>
                        <TableCell class="pl-0">{{ item.price }}</TableCell>
                        <TableCell class="flex items-center justify-center">
                            <Button size="sm" variant="outline" class="text-green-600 border-green-600 hover:bg-green-50" @click="openReceptionDialog(item)">Recepcionar</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div v-else class="text-gray-500">No hay telas añadidas.</div>

        <!-- Diálogo para Recepcionar Ítem -->
        <Dialog v-model:open="receptionDialogOpen">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Recepcionar Tela</DialogTitle>
                </DialogHeader>
                <div v-if="receptionItem">
                    <!-- Tabla Informativa -->
                    <Table class="mb-6">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tela</TableHead>
                                <TableHead>Base de Tela</TableHead>
                                <TableHead>Color del Proveedor</TableHead>
                                <TableHead>Código de Color</TableHead>
                                <TableHead>Cantidad</TableHead>
                                <TableHead>Ancho Estimado</TableHead>
                                <TableHead>Precio</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell>{{ receptionItem.fabric }}</TableCell>
                                <TableCell>{{ receptionItem.base_fabric?.name || 'N/A' }}</TableCell>
                                <TableCell>{{ receptionItem.provider_color }}</TableCell>
                                <TableCell>{{ receptionItem.provider_color_code }}</TableCell>
                                <TableCell>{{ receptionItem.quantity }}</TableCell>
                                <TableCell>{{ receptionItem.estimated_width }}</TableCell>
                                <TableCell>{{ receptionItem.price }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Formulario -->
                    <form @submit.prevent="registerModel">
                        <div class="space-y-4">
                            <InputField v-model="fabricRoll.location" label="Ubicación" placeholder="Ubicación" />
                            <SelectField
                                v-model="fabricRoll.fabric_color_id"
                                :options="[]"
                                label="Color de Tela"
                                placeholder="Seleccione un color"
                            />
                            <InputField v-model="fabricRoll.fabric_order_item_id" label="ID del Ítem" :disabled="true" />
                            <InputField v-model="fabricRoll.received_quantity" label="Cantidad Recibida" type="number" step="0.01" placeholder="Cantidad Recibida" />
                            <InputField v-model="fabricRoll.width" label="Ancho" type="number" step="0.01" placeholder="Ancho" />
                            <InputField v-model="fabricRoll.quantity" label="Cantidad" type="number" step="0.01" placeholder="Cantidad" />
                        </div>
                        <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                            <div class="w-full md:w-auto">
                                <button
                                    type="button"
                                    class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                    @click="closeReceptionDialog"
                                >
                                    Cancelar
                                </button>
                            </div>
                            <div class="w-full md:w-auto">
                                <Button
                                    type="submit"
                                    class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                                >
                                    Registrar
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </div>
                <div v-else>
                    <p class="text-red-500">No se ha seleccionado ningún ítem para recepcionar.</p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
