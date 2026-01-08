<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import ProcessSelector from '@/components/ProcessSelector.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { ProductSheet } from '@/domain/product-sheet';
import { computed, ref, watch } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import axios from 'axios';

interface Props {
    mode: 'create' | 'edit'; // Define si es creación o edición
    productSheet?: ProductSheet; // Producto a editar (opcional)
    sections: any[];
    groups: any[];
    brands: any[];
    worlds: any[];
    fabricBases: any[]; // Cambiar bases a fabricBases
    yarnBases: any[]; // Asegúrate de que el tipo sea un arreglo
}

const props = defineProps<Props & { open: boolean }>(); // Agregar la propiedad `open`
const emit = defineEmits(['productSheetSaved', 'update:open']); // Agregar `update:open`

const localDialogOpen = ref(false); // Estado local para el diálogo

// Alert state
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);
const dialogInnerRef = ref<HTMLElement | null>(null);
const isLoading = ref(false);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    if (dialogInnerRef.value && typeof dialogInnerRef.value.scrollTo === 'function') {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
}

const form = ref<Partial<ProductSheet>>({
    reference: '',
    section_id: 0,
    group_id: 0,
    brand_id: 0,
    world_id: 0,
    fabric_base_id: 0, // Asegurarse de que este campo tenga un valor predeterminado
    yarn_base_id: 0, // Nuevo campo para la base hilaza
    billing_price: 0,
    selling_price: 0,
    sizes: JSON.stringify({
        XS: false,
        S: false,
        M: false,
        L: false,
        XL: false,
        '2XL': false,
        '2': false,
        '3': false,
        '4': false,
        '5': false,
        '6': false,
        '8': false,
        '10': false,
        '12': false,
        '14': false,
        '16': false,
        '28': false,
        '30': false,
        '32': false,
        '34': false,
        '36': false,
        '38': false,
    }),
    woven_product: false,
    description: '',
    processes: '',
    status: 'incomplete',
});

const sizeGroups = {
    tallas_1: ['XS', 'S', 'M', 'L', 'XL', '2XL'],
    tallas_2: ['2', '3', '4', '5', '6', '8', '10', '12', '14', '16'],
    tallas_3: ['28', '30', '32', '34', '36', '38'],
};

const parsedSizes = computed<Record<string, boolean>>(() => {
    const sizesValue = form.value.sizes;
    if (!sizesValue) return {};
    if (typeof sizesValue === 'string') {
        try {
            const parsed = JSON.parse(sizesValue);
            if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                return parsed as Record<string, boolean>;
            }
        } catch {
            // ignore invalid JSON and fall back to empty record
        }
        return {};
    }
    if (typeof sizesValue === 'object' && !Array.isArray(sizesValue)) {
        return sizesValue as Record<string, boolean>;
    }
    return {};
});

const overallStatus = computed<boolean>(() => {
    const hasSelectedSize = Object.values(parsedSizes.value).some((value) => value === true);
    return (
        Boolean(form.value.reference) &&
        Boolean(form.value.billing_price) &&
        Boolean(form.value.selling_price) &&
        hasSelectedSize
    );
});

const allProcesses = ref(['Tejido', 'Corte', 'Bordado', 'Sublimado', 'Estampado', 'Lavanderia', 'Muestra', 'Confección', 'Terminación', 'Bodega']);

watch(
    () => form.value.woven_product,
    (newValue) => {
        const processes = JSON.parse(form.value.processes || '{}');
        if (newValue) {
            if (!processes['Tejido']) {
                processes['Tejido'] = true;
            }
        } else {
            delete processes['Tejido'];
        }
        form.value.processes = JSON.stringify(processes);
    },
);

watch(
    () => props.productSheet,
    (newProductSheet) => {
        if (props.mode === 'edit' && newProductSheet) {
            form.value = { ...newProductSheet }; // Clonar los datos del producto para evitar referencias directas
            try {
                form.value.sizes = typeof newProductSheet.sizes === 'string'
                    ? newProductSheet.sizes // Mantener como cadena si ya es una cadena JSON
                    : JSON.stringify(newProductSheet.sizes || {}); // Convertir a cadena si es un objeto
            } catch (error) {
                console.error('Error al analizar sizes:', error);
                form.value.sizes = JSON.stringify({}); // Restablecer a una cadena JSON vacía en caso de error
            }
        }
    },
    { immediate: true }
);

watch(
    () => props.open,
    (newValue) => {
        if (localDialogOpen.value !== newValue) {
            localDialogOpen.value = newValue;
            if (!newValue) {
                resetForm(); // Restablecer el formulario si el diálogo se cierra desde el padre
            } else if (props.mode === 'create') {
                resetForm(); // Restablecer el formulario al abrir en modo "crear"
            }
        }
    },
    { immediate: true }
);

watch(
    () => localDialogOpen.value,
    (newValue) => {
        if (props.open !== newValue) { // Evitar actualizaciones redundantes
            emit('update:open', newValue);
        }
    }
);

function determineStatus() {
    form.value.status = overallStatus.value ? 'complete' : 'incomplete';
}

function saveProductSheet() {
    if (!validateForm()) {
        setAlert('Por favor, completa todos los campos requeridos.', 'warning');
        return;
    }

    determineStatus();
    isLoading.value = true;

    const payload = { ...form.value };
    payload.sizes = form.value.sizes || JSON.stringify({}); // Asegurar que las tallas estén serializadas como cadena JSON

    const request = props.mode === 'edit'
        ? axios.put(`/diseno/sabana-productos/${props.productSheet?.id}`, payload)
        : axios.post('/diseno/sabana-productos', payload);

    request
        .then((response) => {
            setAlert(`Product sheet ${props.mode === 'edit' ? 'actualizado' : 'creado'} exitosamente.`, 'success');
            emit('productSheetSaved', response.data);
            resetForm();
            localDialogOpen.value = false;
        })
        .catch((error) => {
            console.error(`Error al ${props.mode === 'edit' ? 'actualizar' : 'crear'} el product sheet:`, error);
            setAlert(`Hubo un error al ${props.mode === 'edit' ? 'actualizar' : 'crear'} el product sheet.`, 'error');
        })
        .finally(() => {
            isLoading.value = false;
        });
}

function validateForm() {
    const errors = [];
    if (!form.value.section_id) errors.push('Sección es requerida.');
    if (!form.value.group_id) errors.push('Grupo es requerido.');
    if (!form.value.brand_id) errors.push('Marca es requerida.');
    if (!form.value.world_id) errors.push('Mundo es requerido.');
    if (!form.value.fabric_base_id) errors.push('Base es requerida.'); // Cambiar base_id a fabric_base_id

    if (errors.length > 0) {
        setAlert(errors.join('\n'), 'warning');
        return false;
    }
    return true;
}

function resetForm() {
    form.value = {
        reference: '',
        section_id: 0,
        group_id: 0,
        brand_id: 0,
        world_id: 0,
        fabric_base_id: 0, // Cambiar base_id a fabric_base_id
        yarn_base_id: 0, // Nuevo campo para la base hilaza
        billing_price: 0,
        selling_price: 0,
        sizes: JSON.stringify({ // Restablecer todas las tallas a `false`
            XS: false,
            S: false,
            M: false,
            L: false,
            XL: false,
            '2XL': false,
            '2': false,
            '3': false,
            '4': false,
            '5': false,
            '6': false,
            '8': false,
            '10': false,
            '12': false,
            '14': false,
            '16': false,
            '28': false,
            '30': false,
            '32': false,
            '34': false,
            '36': false,
            '38': false,
        }),
        woven_product: false,
        description: '',
        processes: '',
        status: 'incomplete',
    };
}

function handleClose() {
    resetForm(); // Restablecer el formulario al cerrar el diálogo
    if (localDialogOpen.value) {
        localDialogOpen.value = false; // Asegurar que el estado local se actualice
    }
    if (props.open) {
        emit('update:open', false); // Sincronizar el estado con el componente padre
    }
}
</script>

<template>
    <Dialog v-model:open="localDialogOpen" @close="handleClose">
        <DialogContent class="max-h-[90vh] flex flex-col sm:max-w-4xl">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ props.mode === 'edit' ? 'Editar' : 'Crear' }} Sabana de Productos</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.mode === 'edit' ? 'editar' : 'crear' }} un producto. Haz clic en guardar cuando termines.
                </DialogDescription>
            </DialogHeader>
            
            <div ref="dialogInnerRef" class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <transition name="fade">
                    <div v-if="showAlert && alertMessage" class="mb-4">
                        <AlertBox
                            :type="alertType"
                            :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : alertType === 'info' ? 'Información' : 'Error'"
                            :message="alertMessage"
                            class="pointer-events-auto"
                            @close="clearAlert"
                        />
                    </div>
                </transition>

            <!-- Primera sección: Identificación del producto -->
            <div class="grid grid-cols-3 gap-4 mb-4">
                <InputField v-model="form.reference" label="Referencia" placeholder="ejemplo: 901010" />
                <InputField v-model="form.billing_price" type="currency" label="Precio Facturación" placeholder="$ 0" />
                <InputField v-model="form.selling_price" type="currency" label="Precio de Venta" placeholder="$ 0" />
            </div>

            <!-- Segunda sección: Clasificación -->
            <div class="grid grid-cols-4 gap-4 mb-4">
                <SelectField v-model="form.section_id" label="Sección" :options="props.sections" required />
                <SelectField v-model="form.group_id" label="Grupo" :options="props.groups" required />
                <SelectField v-model="form.brand_id" label="Marca" :options="props.brands" required />
                <SelectField class="w-full" v-model="form.world_id" label="Mundo" :options="props.worlds" required />
            </div>

            <!-- Nueva sección: Mundo, Base, Tallas y Producto Tejido -->
            <div :class="`grid gap-4 mb-4 ${form.woven_product ? 'grid-cols-4' : 'grid-cols-3'}`">
                <div class="flex items-center w-auto">
                    <SwitchField v-model="form.woven_product" label="Producto Tejido" id="woven_product" required />
                </div>
                <SelectField class="w-full" v-model="form.fabric_base_id" label="Base" :options="props.fabricBases" required /> <!-- Asegurar que esté enlazado -->
                <SelectField
                    v-if="form.woven_product"
                    class="w-full"
                    v-model="form.yarn_base_id"
                    label="Base Hilaza"
                    :options="props.yarnBases"
                    required
                />
                <SizeSelector class="w-full" v-model="form.sizes" :sizeGroups="sizeGroups" />
            </div>
            
            <!-- Tercera sección: Propiedades adicionales -->
            <div class="grid grid-cols-1 gap-4 mb-4">
                <InputField
                    v-model="form.description"
                    label="Descripción de Producto"
                    type="text"
                    placeholder="Añade descripción o observaciones del producto"
                />
            </div>

            <!-- Cuarta sección: Procesos -->
            <div>
                <ProcessSelector
                    v-model="form.processes"
                    :availableProcesses="allProcesses"
                    :wovenProduct="form.woven_product"
                />
            </div>
            </div>

            <!-- Botón Guardar -->
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="handleClose"
                    >
                        Cancelar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        size="sm"
                        type="button"
                        :class="[
                            'mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm',
                            overallStatus === false ? 'border border-red-600 bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500' : '',
                        ]"
                        :disabled="isLoading"
                        @click="saveProductSheet"
                    >
                        {{ props.mode === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
