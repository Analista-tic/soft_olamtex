<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import type { Contact } from '@/types';

const props = defineProps<{
    // Hacemos opcional y permisivo para evitar errores de tipado aquí.
    form?: Partial<Contact> | Record<string, any>;
}>();

const emit = defineEmits<{
    (e: 'update:form', updatedForm: Partial<Contact>): void;
}>();

// Defaults para evitar errores cuando props.form sea undefined
const defaultForm: Partial<Contact> = {
    contact_type: '',
    id_type: '',
    id_number: '',
    dv: '',
    full_name: '',
    person_type: '',
    groups: [],
    municipality: '',
    department: '',
    address: '',
    email: '',
    phone: '',
};

// Inicializar de forma segura: merge de defaults con el prop (si existe)
const internalForm = ref<Partial<Contact>>(Object.assign({}, defaultForm, props.form || {}));

// Sincronizar el objeto interno con el prop `form` sin causar recursividad
watch(
    () => props.form,
    (newForm) => {
        if (JSON.stringify(newForm) !== JSON.stringify(internalForm.value)) {
            internalForm.value = { ...newForm };
        }
    },
    { deep: true },
);

// Emitir actualizaciones al padre cuando cambie el objeto interno
watch(
    internalForm,
    (updatedForm) => {
        if (JSON.stringify(updatedForm) !== JSON.stringify(props.form)) {
            emit('update:form', updatedForm); // Emitir el formulario actualizado
        }
    },
    { deep: true },
);

const colombiaData = ref<{ id: string; name: string; municipalities: string[] }[]>([]);

async function loadColombiaData() {
    try {
        const response = await axios.get('https://raw.githubusercontent.com/marcovega/colombia-json/master/colombia.min.json');
        colombiaData.value = response.data.map((department: any) => ({
            id: department.departamento, // Usar el nombre del departamento como ID
            name: department.departamento,
            municipalities: department.ciudades || [], // Asegurar que siempre sea un arreglo
        }));
    } catch (error) {
        console.error('Error al cargar los datos de Colombia:', error);
    }
}

const groups = ref<{ id: string; name: string }[]>([]); // Lista de grupos

async function loadGroups() {
    try {
        const response = await axios.get('categorias/grupos'); // Asegúrate de que el endpoint sea correcto
        groups.value = response.data.map((group: any) => ({
            id: group.id, // Asegúrate de que el campo `id` exista en los datos
            name: group.name, // Asegúrate de que el campo `name` exista en los datos
        }));
    } catch (error) {
        console.error('Error al cargar los grupos:', error);
    }
}

onMounted(() => {
    loadColombiaData();
    loadGroups(); // Cargar grupos al montar
});

const errors = ref<Record<string, string[]>>({}); // Manejo de errores

// Opciones combinadas "Municipio, Departamento"
const locationOptions = computed(() =>
    colombiaData.value.flatMap((department) =>
        (department.municipalities || []).map((municipality: string) => ({
            id: `${municipality}||${department.id}`,
            name: `${municipality}, ${department.name}`,
        })),
    ),
);

const locationValue = computed({
    get() {
        if (!internalForm.value.municipality || !internalForm.value.department) return '';
        return `${internalForm.value.municipality}||${internalForm.value.department}`;
    },
    set(val: string | undefined) {
        if (!val) {
            internalForm.value.municipality = '';
            internalForm.value.department = '';
            return;
        }
        const [municipality, department] = val.split('||');
        internalForm.value.municipality = municipality || '';
        internalForm.value.department = department || '';
    },
});

// Usar string aquí evita errores si la clave no está exactamente en la interfaz Contact.
const validateField = (field: string) => {
    if (errors.value[field]) {
        delete errors.value[field];
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 space-y-4">
        <SelectField
            v-model="internalForm.contact_type"
            label="Tipo de contacto"
            required
            :options="[
                { id: 'Proveedor', name: 'Proveedor' },
                { id: 'Cliente', name: 'Cliente' },
                { id: 'Cliente y Proveedor', name: 'Cliente y Proveedor' },
            ]"
            :error="errors.contact_type?.[0]"
            :searchable="false"
            @input="validateField('contact_type')"
        />
        <SelectField
            v-model="internalForm.groups"
            label="Categoría proveedor/cliente"
            required
            multiple
            :options="groups.map((group) => ({ id: group.id, name: group.name }))"
            :error="errors.groups?.[0]"
            @input="validateField('groups')"
        />
        <SelectField
            v-model="internalForm.id_type"
            label="Tipo de identificación"
            required
            :options="[
                { id: 'NIT', name: 'NIT - Número de identificación tributaria' },
                { id: 'CC', name: 'CC - Cédula de ciudadanía' },
                { id: 'NIT de otro país', name: 'NIT de otro país' },
                { id: 'PP', name: 'PP - Pasaporte' },
                { id: 'PEP', name: 'PEP - Permiso especial de permanencia' },
                { id: 'DIE', name: 'DIE - Documento de identificación extranjero' },
                { id: 'CE', name: 'CE - Cédula de extranjería' },
                { id: 'TE', name: 'TE - Tarjeta de extranjería' },
                { id: 'TI', name: 'TI - Tarjeta de identidad' },
                { id: 'RC', name: 'RC - Registro civil' },
                { id: 'NUIP', name: 'NUIP - Número único de identificación personal' },
                { id: 'PPT', name: 'PPT - Permiso de protección temporal' },
            ]"
        />
        <div class="grid grid-cols-5 gap-4 mb-4">
            <div class="col-span-4">
                <InputField
                    v-model="internalForm.id_number"
                    label="Número de identificación"
                    required
                    placeholder="Número de identificación"
                />
            </div>
            <div class="col-span-1">
                <InputField v-model="internalForm.dv" label="DV" placeholder="DV" />
            </div>
        </div>
        <InputField
            v-model="internalForm.full_name"
            label="Nombre completo o razón social"
            required
            placeholder="Nombre completo"
            class="mb-4"
        />
        <SelectField
            v-model="internalForm.person_type"
            label="Tipo de persona"
            required
            :options="[
                { id: 'Persona natural', name: 'Persona natural' },
                { id: 'Persona jurídica', name: 'Persona jurídica' },
            ]"
            class="mb-4"
        />
        <div class="md:col-span-2">
            <SelectField
                v-model="locationValue"
                label="Departamento y municipio"
                required
                :options="locationOptions"
                placeholder="Selecciona municipio y departamento"
                :error="errors.municipality?.[0] || errors.department?.[0]"
                @input="() => { validateField('municipality'); validateField('department'); }"
            />
        </div>
        <div class="col-span-full grid grid-cols-3 gap-4 mb-4">
            <InputField v-model="internalForm.address" label="Dirección" placeholder="Dirección" />
            <InputField
                v-model="internalForm.email"
                label="Correo electrónico"
                type="email"
                required
                placeholder="Correo electrónico"
                :error="!!errors.email?.[0]"
                @input="validateField('email')"
            />
            <InputField v-model="internalForm.phone" label="Teléfono" placeholder="Teléfono" />
        </div>
    </div>
</template>

<style scoped>
/* Agregar estilos específicos si es necesario */
</style>
