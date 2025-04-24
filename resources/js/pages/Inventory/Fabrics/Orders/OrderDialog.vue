<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { FabricOrder, FabricOrderItem } from '@/types';
import axios from 'axios';
import { onMounted, ref, watch, computed } from 'vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import OrderItemManager from '@/pages/Inventory/Fabrics/Orders/OrderItemManager.vue';
import type { PropType } from 'vue';

const props = defineProps({
    open: Boolean,
    dialogType: {
        type: String as PropType<'create' | 'edit'>,
        required: true,
    },
    form: {
        type: Object as PropType<FabricOrder>,
        required: true,
    },
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'refresh'): void;
}>();

const items = ref<FabricOrderItem[]>([]);

const internalOpen = ref(props.open);

const providerOptions = ref<{ id: number; name: string }[]>([]);
const baseFabricOptions = ref<{ id: number; name: string }[]>([]);

async function fetchProviderIds() {
    try {
        const response = await axios.get('/providers'); // Ruta corregida
        providerOptions.value = response.data.map((provider: { id: number; name: string }) => ({
            id: provider.id,
            name: provider.name,
        }));
    } catch (error) {
        console.error('Error al obtener los proveedores:', error);
    }
}

async function fetchBaseFabrics() {
    try {
        const response = await axios.get('/base-fabrics'); // Ruta corregida
        baseFabricOptions.value = response.data.map((baseFabric: { id: number; name: string }) => ({
            id: baseFabric.id,
            name: baseFabric.name,
        }));
    } catch (error) {
        console.error('Error al obtener las bases de tela:', error);
    }
}

onMounted(() => {
    fetchProviderIds();
    fetchBaseFabrics();
});

watch(
    () => props.open,
    (newValue) => {
        internalOpen.value = newValue;
    },
);

watch(
    () => internalOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('update:open', false);
        }
    },
);

const localForm = ref<FabricOrder>({
    id: props.form?.id ?? null,
    fabric_provider_id: props.form?.fabric_provider_id ?? '',
    status: props.form?.status ?? '',
    items: props.form?.items ?? [],
});

watch(
    () => props.form,
    (newForm) => {
        if (newForm) {
            localForm.value = {
                id: newForm.id ?? null, // Cambiar undefined por null
                fabric_provider_id: newForm.fabric_provider_id ?? '',
                status: newForm.status ?? '',
                items: newForm.items ?? [],
            };
            items.value = newForm.items || [];
        } else {
            localForm.value = {
                id: null, // Cambiar undefined por null
                fabric_provider_id: '',
                status: '',
                items: [],
            };
            items.value = [];
        }
    },
    { immediate: true },
);

const computedId = computed({
    get: () => localForm.value.id?.toString() ?? '', // Convertir a cadena
    set: (value: string | number) => { // Aceptar cadenas o números
        localForm.value.id = value ? parseInt(value.toString(), 10) : null; // Convertir a número o null
    },
});

const statusTranslations: Record<string, string> = {
    not_received: 'No recibido',
    in_reception: 'En recepción',
    received: 'Recibido',
};

const translatedStatus = computed(() => statusTranslations[localForm.value.status] ?? localForm.value.status);

async function submitForm() {
    if (!localForm.value.fabric_provider_id) {
        console.error('El campo Proveedor es obligatorio.');
        return;
    }

    if (items.value.length === 0) {
        console.error('El campo items debe contener al menos un elemento.');
        return;
    }

    try {
        const payload = {
            ...localForm.value,
            fabric_provider_id: String(localForm.value.fabric_provider_id), // Convertir a cadena
            items: items.value.map(item => ({
                ...item,
                base_fabric_id: String(item.base_fabric_id), // Corregir clave a base_fabric_id
                price: parseFloat(item.price.toString()), // Asegurar que el precio sea un número
            })),
        };

        const url = props.dialogType === 'create' 
            ? '/inventario/pedidos-telas/' 
            : `/inventario/pedidos-telas/${localForm.value.id}`;
        const method = props.dialogType === 'create' ? 'POST' : 'PUT';

        await axios({ method, url, data: payload });
        emit('refresh');
        emit('update:open', false); // Cerrar el diálogo tras guardar
    } catch (error) {
        console.error('Error al guardar el pedido:', error);
    }
}

</script>

<template>
    <Dialog v-if="internalOpen" v-model:open="internalOpen">
        <DialogContent class="max-h-[80vh] w-[90vw] max-w-5xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'create' ? 'Crear Pedido' : 'Editar Pedido' }} de Telas</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo pedido' : 'editar el pedido' }}.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitForm">
                <!-- Información del Pedido -->
                <div class="space-y-6">
                    <div :class="`grid gap-4 ${dialogType === 'edit' ? 'grid-cols-3' : 'grid-cols-2'}`">
                        <!-- Número de Orden -->
                        <InputField
                            v-model="computedId"
                            label="Número de Orden"
                            placeholder="Número de Orden"
                            readonly
                            disabled
                        />
                        <!-- Proveedor -->
                        <SelectField
                            v-model="localForm.fabric_provider_id"
                            :options="providerOptions"
                            label="Proveedor"
                        />
                        <!-- Estado (No editable) -->
                        <InputField
                            v-if="dialogType === 'edit'"
                            :modelValue="translatedStatus"
                            label="Estado"
                            placeholder="Estado"
                            disabled
                            readonly
                        />
                    </div>
                </div>

                <OrderItemManager 
                    class="mt-6"
                    :items="items" 
                    :baseFabricOptions="baseFabricOptions" 
                    @update:items="(updatedItems) => items = updatedItems" 
                />

                <!-- Botones de Acción -->
                <DialogFooter class="mt-6">
                    <Button variant="outline" @click="emit('update:open', false)">Cancelar</Button>
                    <Button size="sm" type="submit">Guardar</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
