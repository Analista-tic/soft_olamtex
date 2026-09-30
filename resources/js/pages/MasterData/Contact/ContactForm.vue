<script setup lang="ts">
import GroupSelect from '@/components/GroupSelect.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Contact, ContactIdType, ContactPersonType, ContactType } from '@/domain/MasterData';
import { useMasterDataStore } from '@/stores/masterData/masterData';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
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
    { id: 'cliente', name: 'Cliente' },
    { id: 'cliente_proveedor', name: 'Cliente y Proveedor' },
    { id: 'proveedor', name: 'Proveedor' },
];

const idTypeOptions = [
    { id: 'cc', name: 'CC - Cedula de ciudadania' },
    { id: 'ce', name: 'CE - Cedula de extranjeria' },
    { id: 'die', name: 'DIE - Documento de identificacion extranjero' },
    { id: 'nit', name: 'NIT - Número de identificación tributaria' },
    { id: 'nit_otro_pais', name: 'NIT de otro pais' },
    { id: 'nuip', name: 'NUIP - Número único de identificación personal' },
    { id: 'pep', name: 'PEP - Permiso especial de permanencia' },
    { id: 'pp', name: 'PP - Pasaporte' },
    { id: 'ppt', name: 'PPT - Permiso de proteccion temporal' },
    { id: 'rc', name: 'RC - Registro civil' },
    { id: 'te', name: 'TE - Tarjeta de extranjeria' },
    { id: 'ti', name: 'TI - Tarjeta de identidad' },
];

const personTypeOptions = [
    { id: 'persona_juridica', name: 'Persona juridica' },
    { id: 'persona_natural', name: 'Persona natural' },
];

const props = defineProps<{
    form?: ContactFormState | Record<string, any>;
    errors?: Record<string, string[]>;
}>();

const emit = defineEmits<{
    (e: 'update:form', updatedForm: ContactFormState): void;
    (e: 'clear-error', field: string): void;
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

const allLocationOptions = colombiaData
    .flatMap((department: any) =>
        (department.ciudades || []).map((municipality: string) => ({
            id: `${municipality}||${department.departamento}`,
            name: `${municipality}, ${department.departamento}`,
            searchText: `${municipality.toLowerCase()} ${department.departamento.toLowerCase()}`,
        })),
    )
    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));

const locationOptions = ref(allLocationOptions.slice(0, 50));
const isLoadingLocations = ref(false);

const getSelectedLocationKey = () => {
    if (!internalForm.value.municipality || !internalForm.value.department) return '';
    return `${internalForm.value.municipality}||${internalForm.value.department}`;
};

const syncSelectedLocationOption = (key: string) => {
    if (!key || locationOptions.value.some((opt) => opt.id === key)) return;

    const match = allLocationOptions.find((opt) => opt.id === key);
    if (!match) return;

    locationOptions.value = [match, ...locationOptions.value.filter((opt) => opt.id !== key).slice(0, 49)];
};

const updateLocationOptions = (searchQuery: string = '') => {
    if (isLoadingLocations.value) return;

    const query = searchQuery.toLowerCase().trim();

    if (!query) {
        locationOptions.value = allLocationOptions.slice(0, 50);
        syncSelectedLocationOption(getSelectedLocationKey());
        return;
    }

    isLoadingLocations.value = true;

    setTimeout(() => {
        locationOptions.value = allLocationOptions.filter((opt) => opt.searchText.includes(query)).slice(0, 100);
        syncSelectedLocationOption(getSelectedLocationKey());
        isLoadingLocations.value = false;
    }, 0);
};

const masterDataStore = useMasterDataStore();

const errors = computed(() => props.errors ?? {});

const errorFieldKeys = ['contact_type', 'groups', 'id_type', 'id_number', 'full_name', 'person_type', 'municipality', 'department', 'email'] as const;

errorFieldKeys.forEach((field) => {
    watch(
        () => JSON.stringify(internalForm.value[field] ?? ''),
        () => {
            if (errors.value[field]) emit('clear-error', field);
        },
    );
});

watch(getSelectedLocationKey, (key) => {
    syncSelectedLocationOption(key);
}, { immediate: true });

const locationValue = computed({
    get() {
        return getSelectedLocationKey();
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
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
        <SelectField
            v-model="internalForm.contact_type"
            label="Tipo de contacto"
            required
            :options="contactTypeOptions"
            :invalid="!!errors.contact_type"
            :error-message="errors.contact_type?.[0]"
            :searchable="false"
        />
        <GroupSelect
            v-model="internalForm.groups"
            label="Categoría proveedor/cliente"
            :options="masterDataStore.groups"
            placeholder="Selecciona categorías"
            required
            :searchable="true"
            :error="errors.groups?.[0]"
        />
        <SelectField
            v-model="internalForm.id_type"
            label="Tipo de identificacion"
            required
            :searchable="true"
            :options="idTypeOptions"
            :invalid="!!errors.id_type"
            :error-message="errors.id_type?.[0]"
        />
        <div class="grid grid-cols-5 gap-4">
            <div :class="internalForm.id_type === 'nit' ? 'col-span-4' : 'col-span-5'">
                <InputField
                    v-model="internalForm.id_number"
                    label="Número de identificación"
                    required
                    placeholder="Número de identificación"
                    :error="errors.id_number?.[0]"
                />
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
        <SelectField
            v-model="internalForm.person_type"
            label="Tipo de persona"
            required
            :options="personTypeOptions"
            :invalid="!!errors.person_type"
            :error-message="errors.person_type?.[0]"
            :searchable="false"
        />
        <InputField
            v-model="internalForm.full_name"
            data-no-uppercase
            label="Nombre completo o razón social"
            required
            placeholder="Nombre completo"
            :error="errors.full_name?.[0]"
        />
        <div class="md:col-span-2">
            <SelectField
                v-model="locationValue"
                label="Municipio y departamento"
                required
                :options="locationOptions"
                placeholder="Escribe para buscar municipio o departamento..."
                :invalid="!!(errors.municipality || errors.department)"
                :error-message="errors.municipality?.[0] || errors.department?.[0]"
                :searchable="true"
                :searchThreshold="0"
                @search="updateLocationOptions"
            />
        </div>
        <div class="col-span-full grid grid-cols-3 gap-4">
            <InputField v-model="internalForm.address" data-no-uppercase label="Dirección" placeholder="Dirección" />
            <InputField
                v-model="internalForm.email"
                label="Correo electrónico"
                type="email"
                required
                placeholder="Correo electrónico"
                :error="errors.email?.[0]"
            />
            <InputField v-model="internalForm.phone" label="Teléfono" placeholder="Teléfono" />
        </div>
    </div>
</template>

<style scoped>
</style>
