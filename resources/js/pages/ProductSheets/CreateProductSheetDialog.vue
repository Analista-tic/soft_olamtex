<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import ProcessSelector from '@/components/ProcessSelector.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ProductSheet } from '@/types';
import { PlusCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps(['sections', 'groups', 'brands', 'worlds', 'bases']);
const emit = defineEmits(['productSheetCreated']);

const isDialogOpen = ref(false);

const form = ref<Partial<ProductSheet>>({
    reference: '',
    section_id: 0,
    group_id: 0,
    brand_id: 0,
    world_id: 0,
    base_id: 0,
    billing_price: 0,
    selling_price: 0,
    sizes: JSON.stringify({
        'XS': false, 'S': false, 'M': false, 'L': false, 'XL': false, '2XL': false,
        '2': false, '3': false, '4': false, '5': false, '6': false, '8': false, '10': false, '12': false, '14': false, '16': false,
        '28': false, '30': false, '32': false, '34': false, '36': false, '38': false,
    }), // Guardado como JSON en texto plano
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

const allProcesses = ref(['Tejido', 'Corte', 'Bordado', 'Sublimado', 'Estampado', 'Lavanderia', 'Muestra', 'Confección', 'Terminación', 'Bodega']);

watch(() => form.value.woven_product, (newValue) => {
    const processes = JSON.parse(form.value.processes || '{}');
    if (newValue) {
        if (!processes['Tejido']) {
            processes['Tejido'] = true;
        }
    } else {
        delete processes['Tejido'];
    }
    form.value.processes = JSON.stringify(processes);
});

function determineStatus() {
    if (
        form.value.reference &&
        form.value.billing_price &&
        form.value.selling_price &&
        Object.values(JSON.parse(form.value.sizes || '{}')).some(size => size)
    ) {
        form.value.status = 'complete';
    } else {
        form.value.status = 'incomplete';
    }
}

function createProductSheet() {
    validateForm();
    determineStatus();

    axios.post('/comercial/sabana-productos', {
        ...form.value,
        sizes: form.value.sizes || JSON.stringify({}) // Asegurarse de que sizes sea una cadena
    })
        .then((response) => {
            alert('Product sheet creado exitosamente.');
            emit('productSheetCreated', response.data); // Emitir solo el objeto `data`
            resetForm();
            isDialogOpen.value = false; // Cerrar el diálogo
        })
        .catch(error => {
            console.error('Error al crear el product sheet:', error);
            alert('Hubo un error al crear el product sheet.');
        });
}

function validateForm() {
    const warnings = [];
    if (!form.value.section_id) warnings.push('Sección es requerida.');
    if (!form.value.group_id) warnings.push('Grupo es requerido.');
    if (!form.value.brand_id) warnings.push('Marca es requerida.');
    if (!form.value.world_id) warnings.push('Mundo es requerido.');
    if (!form.value.base_id) warnings.push('Base es requerida.');
    if (!form.value.processes || Object.keys(JSON.parse(form.value.processes || '{}')).length === 0) warnings.push('Al menos un proceso es requerido.');

    if (warnings.length > 0) {
        alert(warnings.join('\n'));
    }
}

function resetForm() {
    form.value = {
        reference: '',
        section_id: 0,
        group_id: 0,
        brand_id: 0,
        world_id: 0,
        base_id: 0,
        billing_price: 0,
        selling_price: 0,
        sizes: JSON.stringify({
            'XS': false, 'S': false, 'M': false, 'L': false, 'XL': false, '2XL': false,
            '2': false, '3': false, '4': false, '5': false, '6': false, '8': false, '10': false, '12': false, '14': false, '16': false,
            '28': false, '30': false, '32': false, '34': false, '36': false, '38': false,
        }), // Guardado como JSON en texto plano
        woven_product: false,
        description: '',
        processes: '', // Inicializado como JSON en texto plano
        status: 'incomplete',
    };
}
</script>

<template>
    <Dialog @open="resetForm" v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="default" size="sm"><PlusCircle class="w-4 h-4"/> Crear Nuevo Producto</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-5xl max-h-screen overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Crear Nueva Sabana de Productos</DialogTitle>
                <DialogDescription> Completa los campos para crear un producto. Haz clic en guardar cuando termines. </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-4 gap-y-1">
                <div class="space-y-5">
                    <!-- Información General -->
                    <InputField v-model="form.reference" label="Referencia" placeholder="ejemplo: 901010" />
                    <SelectField v-model="form.section_id" label="Sección" :options="props.sections" required />
                    <SelectField v-model="form.group_id" label="Grupo" :options="props.groups" required />
                    <SelectField v-model="form.brand_id" label="Marca" :options="props.brands" required />
                    <SelectField v-model="form.world_id" label="Mundo" :options="props.worlds" required />
                    <SelectField v-model="form.base_id" label="Base" :options="props.bases" required />
                    <SwitchField v-model="form.woven_product" label="Producto Tejido" id="woven_product" required/>
                </div>
                <div class="space-y-5">
                    <!-- Precios y Tamaños -->
                    <InputField v-model="form.billing_price" label="Precio Facturación" placeholder="$ 0" />
                    <InputField v-model="form.selling_price" label="Precio de Venta" placeholder="$ 0" />
                    <SizeSelector v-model="form.sizes" :sizeGroups="sizeGroups" />
    
                    <!-- Procesos y Descripción -->
                    <ProcessSelector v-model="form.processes" :availableProcesses="allProcesses" :wovenProduct="form.woven_product" />
                </div>
                <div class="col-span-2 mt-3">
                    <InputField v-model="form.description" label="Descripción de Producto" type="textarea" placeholder="Añade descripción o observaciones del producto"/>
                </div>
            </div>

            <DialogFooter class="mt-6 flex justify-end">
                <Button size="sm" type="submit" @click="createProductSheet">
                    Guardar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>