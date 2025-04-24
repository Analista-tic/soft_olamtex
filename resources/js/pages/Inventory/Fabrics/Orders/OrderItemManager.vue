<script setup lang="ts">
import { ref } from 'vue';
import type { FabricOrderItem } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { PlusCircle } from 'lucide-vue-next';
import DeleteButton from '@/components/common/DeleteButton.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';

const props = defineProps<{
    items: FabricOrderItem[];
    baseFabricOptions: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:items', items: FabricOrderItem[]): void;
}>();

const newItem = ref<FabricOrderItem>({
    fabric: '', 
    base_fabric_id: '', // Cambiado de `base_fabrics` a `base_fabric_id`
    provider_color: '',
    provider_color_code: '',
    quantity: 0,
    estimated_width: 0,
    price: 0,
});

const addItemDialogOpen = ref(false);

function addItem() {
    if (newItem.value.base_fabric_id && newItem.value.quantity > 0) { // Cambiado de `base_fabrics` a `base_fabric_id`
        const formattedPrice = newItem.value.price; // Mantener el precio sin cambios

        const updatedItems = [...props.items, { 
            ...newItem.value, 
            fabric: newItem.value.fabric || 'Sin especificar',
            base_fabric_id: String(newItem.value.base_fabric_id), // Cambiado de `base_fabrics` a `base_fabric_id`
            price: formattedPrice 
        }];
        emit('update:items', updatedItems);

        newItem.value = {
            fabric: '',
            base_fabric_id: '', // Cambiado de `base_fabrics` a `base_fabric_id`
            provider_color: '',
            provider_color_code: '',
            quantity: 0,
            estimated_width: 0,
            price: 0,
        };

        addItemDialogOpen.value = false;
    }
}

function removeItem(index: number) {
    const updatedItems = props.items.slice();
    updatedItems.splice(index, 1);
    emit('update:items', updatedItems);
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-md">Telas</h3>
            <Button type="button" variant="default" size="sm" @click="addItemDialogOpen = true"><PlusCircle class="w-4 h-4"/> Añadir Tela</Button>
        </div>
        <div v-if="items.length > 0">
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
                                baseFabricOptions.find(option => option.id === parseInt(item.base_fabric_id.toString()))?.name || 'N/A'
                            }}
                        </TableCell>
                        <TableCell class="pl-0">{{ item.provider_color }}</TableCell>
                        <TableCell class="pl-0">{{ item.provider_color_code }}</TableCell>
                        <TableCell class="pl-0">{{ item.quantity }}</TableCell>
                        <TableCell class="pl-0">{{ item.estimated_width }}</TableCell>
                        <TableCell class="pl-0">{{ item.price }}</TableCell>
                        <TableCell class="flex items-center justify-center">
                            <DeleteButton :onClick="() => removeItem(index)" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div v-else class="text-gray-500">No hay telas añadidas.</div>

        <!-- Diálogo para Añadir Ítems -->
        <Dialog v-model:open="addItemDialogOpen">
            <DialogContent class="max-h-[80vh] w-[100vw] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Añadir Ítem</DialogTitle>
                    <DialogDescription></DialogDescription>
                </DialogHeader>
                <form @submit.prevent="addItem">
                    <div class="space-y-6">
                        <h3 class="font-bold text-lg">Detalles del Ítem</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <SelectField
                                v-model="newItem.base_fabric_id"
                                :options="baseFabricOptions"
                                label="Base de Tela"
                                option-label="name"
                                option-value="id"
                            />
                            <InputField v-model="newItem.fabric" label="Tela" placeholder="Tela" />
                            <InputField v-model="newItem.provider_color" label="Color del Proveedor" placeholder="Color del Proveedor" />
                            <InputField v-model="newItem.provider_color_code" label="Código de Color" placeholder="Código de Color" />
                            <InputField v-model="newItem.quantity" label="Cantidad" type="number" step="0.01" placeholder="Cantidad" />
                            <InputField v-model="newItem.estimated_width" label="Ancho Estimado" type="number" step="0.01" placeholder="Ancho Estimado" />
                            <InputField class="col-span-2" v-model="newItem.price" label="Precio" type="currency" placeholder="Precio" />
                        </div>
                    </div>
                    <DialogFooter class="mt-6">
                        <Button variant="outline" @click="addItemDialogOpen = false">Cancelar</Button>
                        <Button type="submit">Añadir</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
