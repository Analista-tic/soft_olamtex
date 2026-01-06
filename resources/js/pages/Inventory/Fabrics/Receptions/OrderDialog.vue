<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import OrderItemManager from './OrderItemManager.vue';
import type { FabricOrder, FabricOrderItem } from '@/domain/fabric';
import { ref, computed, watch, onMounted } from 'vue';
import type { PropType } from 'vue';
import axios from 'axios';

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

// Estado reactivo
const items = ref<FabricOrderItem[]>([]);
const internalOpen = ref(props.open);
const baseFabricOptions = ref<{ id: number; name: string }[]>([]);

// Formulario local
const localForm = computed(() => ({
    ...props.form,
    id: props.form?.id ?? '',
    fabricProviderId: props.form?.fabricProviderId ?? '',
    status: props.form?.status ?? '',
    items: props.form?.items ?? [],
}));

// Traducción de estados
const statusTranslations: Record<string, string> = {
    not_received: 'No recibido',
    in_reception: 'En recepción',
    received: 'Recibido',
};

const translatedStatus = computed(() => statusTranslations[localForm.value.status] ?? localForm.value.status);

// Funciones
async function fetchBaseFabricOptions() {
    try {
        const response = await axios.get('/base-fabrics');
        baseFabricOptions.value = response.data.map((fabric: { id: number; name: string }) => ({
            id: fabric.id,
            name: fabric.name,
        }));
    } catch (error) {
        console.error('Error al obtener las opciones de base de tela:', error);
    }
}

function closeDialog() {
    emit('update:open', false);
}

// Hooks
onMounted(() => {
    fetchBaseFabricOptions();
});

watch(
    () => props.open,
    (newValue) => (internalOpen.value = newValue),
);

watch(
    () => internalOpen.value,
    (newValue) => {
        if (!newValue) emit('update:open', false);
    },
);

watch(
    () => props.form.items,
    (newItems) => {
        items.value = newItems || [];
    },
    { immediate: true },
);
</script>

<template>
    <Dialog v-if="internalOpen" v-model:open="internalOpen">
        <DialogContent class="max-h-[80vh] w-[90vw] max-w-5xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Detalles del Pedido</DialogTitle>
                <DialogDescription>
                    Visualiza los detalles del pedido seleccionado.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-6">
                <!-- Información del Pedido -->
                <div class="grid grid-cols-3 gap-4">
                    <InputField
                        v-model="localForm.id"
                        label="Número de Orden"
                        disabled
                        readonly
                    />
                    <InputField
                        v-model="localForm.fabricProviderId"
                        label="Proveedor"
                        disabled
                        readonly
                    />
                    <InputField
                        v-model="translatedStatus"
                        label="Estado"
                        disabled
                        readonly
                    />
                </div>

                <!-- Componente para gestionar los ítems -->
                <OrderItemManager 
                    class="mt-6"
                    :items="items" 
                />
            </div>

            <DialogFooter class="mt-6">
                <Button variant="outline" @click="closeDialog">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
