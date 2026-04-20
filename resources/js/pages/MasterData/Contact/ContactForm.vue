<script setup lang="ts">
import GroupSelect from '@/components/GroupSelect.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Contact, ContactIdType, ContactPersonType, ContactType } from '@/domain/MasterData';
import { useMasterDataStore } from '@/stores/masterData';
import { useDebounceFn } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import colombiaData from '../../../data/colombia.json';

type ContactFormState = Partial<Omit<Contact, 'contact_type' | 'id_type' | 'person_type' | 'dv'>> & {
    contact_type?: ContactType | '';
    id_type?: ContactIdType | '';
    person_type?: ContactPersonType | '';
    dv?: string | null;
};

const PRIMOS_DV = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];

function calcularDV(nit: string): string {
    const soloNumeros = nit.replace(/\D/g, '');
    if (!soloNumeros) return '';

    const nitPadded = soloNumeros.padStart(15, '0').slice(-15);
    const suma = nitPadded.split('').reduce((acc, digit, i) => acc + parseInt(digit, 10) * PRIMOS_DV[i], 0);
    const residuo = suma % 11;

    if (residuo === 0) return '0';
    if (residuo === 1) return '1';

    return String(11 - residuo);
}

const contactTypeOptions = [
    { id: 'proveedor', name: 'Proveedor' },
    { id: 'cliente', name: 'Cliente' },
    { id: 'cliente_proveedor', name: 'Cliente y Proveedor' },
];

const idTypeOptions = [
    { id: 'nit', name: 'NIT - Numero de identificacion tributaria' },
    { id: 'cc', name: 'CC - Cedula de ciudadania' },
    { id: 'nit_otro_pais', name: 'NIT de otro pais' },
    { id: 'pp', name: 'PP - Pasaporte' },
    { id: 'pep', name: 'PEP - Permiso especial de permanencia' },
    { id: 'die', name: 'DIE - Documento de identificacion extranjero' },
    { id: 'ce', name: 'CE - Cedula de extranjeria' },
    { id: 'te', name: 'TE - Tarjeta de extranjeria' },
    { id: 'ti', name: 'TI - Tarjeta de identidad' },
    { id: 'rc', name: 'RC - Registro civil' },
    { id: 'nuip', name: 'NUIP - Numero unico de identificacion personal' },
    { id: 'ppt', name: 'PPT - Permiso de proteccion temporal' },
];

const personTypeOptions = [
    { id: 'persona_natural', name: 'Persona natural' },
    { id: 'persona_juridica', name: 'Persona juridica' },
];

const props = defineProps<{
    form?: ContactFormState | Record<string, any>;
}>();

const emit = defineEmits<{
    (e: 'update:form', updatedForm: ContactFormState): void;
}>();

const defaultForm: ContactFormState = {
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

function ensureArray(value: Contact['groups'] | undefined | null): Contact['groups'] {
    if (Array.isArray(value)) {
        return value;
    }
    if (value === undefined || value === null) {
        return [];
    }
    return [value];
}

function normalizeFormPayload(form?: ContactFormState | Record<string, any>): ContactFormState {
    const normalized = { ...(form || {}) } as ContactFormState;
    normalized.groups = ensureArray(normalized.groups);
    return normalized;
}

const internalForm = ref<ContactFormState>(Object.assign({}, defaultForm, normalizeFormPayload(props.form)));

watch(
    [() => internalForm.value.id_type, () => internalForm.value.id_number],
    ([tipo, numero]) => {
        if (tipo !== 'nit') {
            internalForm.value.dv = null;
            return;
        }

        internalForm.value.dv = calcularDV(String(numero ?? ''));
    },
);

watch(
    () => props.form,
    (newForm) => {
        const normalized = normalizeFormPayload(newForm);
        const changed = Object.keys(normalized).some((key) => {
            const k = key as keyof typeof normalized;
            if (k === 'groups') {
                const a = JSON.stringify(internalForm.value.groups ?? []);
                const b = JSON.stringify(normalized.groups ?? []);
                return a !== b;
            }
            return internalForm.value[k] !== normalized[k];
        });
        if (changed) {
            internalForm.value = { ...defaultForm, ...normalized };
        }
    },
    { deep: true },
);

const emitUpdate = useDebounceFn(() => {
    emit('update:form', normalizeFormPayload(internalForm.value));
}, 150);

watch(internalForm, emitUpdate, { deep: true });

const allLocationOptions = colombiaData.flatMap((department: any) =>
    (department.ciudades || []).map((municipality: string) => ({
        id: `${municipality}||${department.departamento}`,
        name: `${municipality}, ${department.departamento}`,
        searchText: `${municipality.toLowerCase()} ${department.departamento.toLowerCase()}`,
    })),
);

const locationOptions = ref(allLocationOptions.slice(0, 50));
const isLoadingLocations = ref(false);

const updateLocationOptions = (searchQuery: string = '') => {
    if (isLoadingLocations.value) return;

    const query = searchQuery.toLowerCase().trim();

    if (!query) {
        locationOptions.value = allLocationOptions.slice(0, 50);
        return;
    }

    isLoadingLocations.value = true;

    setTimeout(() => {
        locationOptions.value = allLocationOptions.filter((opt) => opt.searchText.includes(query)).slice(0, 100);
        isLoadingLocations.value = false;
    }, 0);
};

const masterDataStore = useMasterDataStore();

onMounted(() => {
    masterDataStore.fetchGroups();
});

const errors = ref<Record<string, string[]>>({});
const locationValue = computed({
    get() {
        if (!internalForm.value.municipality || !internalForm.value.department) return '';
        const key = `${internalForm.value.municipality}||${internalForm.value.department}`;
        const exists = locationOptions.value.some((opt) => opt.id === key);

        if (!exists) {
            const match = allLocationOptions.find((opt) => opt.id === key);
            if (match) {
                locationOptions.value = [match, ...locationOptions.value.slice(0, 49)];
            }
        }

        return key;
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

const validateField = (field: string) => {
    if (errors.value[field]) {
        delete errors.value[field];
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
        <SelectField
            v-model="internalForm.contact_type"
            label="Tipo de contacto"
            required
            :options="contactTypeOptions"
            :error="errors.contact_type?.[0]"
            :searchable="false"
            @input="validateField('contact_type')"
        />
        <GroupSelect
            v-model="internalForm.groups"
            label="Categoria proveedor/cliente"
            :options="masterDataStore.groups"
            placeholder="Selecciona categorias"
            required
            :searchable="true"
            :error="errors.groups?.[0]"
            @input="validateField('groups')"
        />
        <SelectField
            v-model="internalForm.id_type"
            label="Tipo de identificacion"
            required
            :searchable="true"
            :options="idTypeOptions"
        />
        <div class="mb-4 grid grid-cols-5 gap-4">
            <div :class="internalForm.id_type === 'nit' ? 'col-span-4' : 'col-span-5'">
                <InputField v-model="internalForm.id_number" label="Numero de identificacion" required placeholder="Numero de identificacion" />
            </div>
            <div v-if="internalForm.id_type === 'nit'" class="col-span-1">
                <div class="grid w-full items-center gap-1.5">
                    <Label for="dv">DV</Label>
                    <Input
                        id="dv"
                        :model-value="internalForm.dv ?? ''"
                        readonly
                        placeholder="-"
                        tabindex="-1"
                        class="cursor-not-allowed bg-muted text-center"
                    />
                </div>
            </div>
        </div>
        <InputField v-model="internalForm.full_name" label="Nombre completo o razon social" required placeholder="Nombre completo" class="mb-4" />
        <SelectField
            v-model="internalForm.person_type"
            label="Tipo de persona"
            required
            :options="personTypeOptions"
            class="mb-4"
            :searchable="false"
        />
        <div class="md:col-span-2">
            <SelectField
                v-model="locationValue"
                label="Municipio y departamento"
                required
                :options="locationOptions"
                placeholder="Escribe para buscar municipio o departamento..."
                :error="errors.municipality?.[0] || errors.department?.[0]"
                :searchable="true"
                :searchThreshold="0"
                @search="updateLocationOptions"
                @input="
                    () => {
                        validateField('municipality');
                        validateField('department');
                    }
                "
            />
        </div>
        <div class="col-span-full mb-4 grid grid-cols-3 gap-4">
            <InputField v-model="internalForm.address" label="Direccion" placeholder="Direccion" />
            <InputField
                v-model="internalForm.email"
                label="Correo electronico"
                type="email"
                required
                placeholder="Correo electronico"
                :error="!!errors.email?.[0]"
                @input="validateField('email')"
            />
            <InputField v-model="internalForm.phone" label="Telefono" placeholder="Telefono" />
        </div>
    </div>
</template>

<style scoped>
</style>
