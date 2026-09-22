<script setup lang="ts">
import ChipInputField from '@/components/ChipInputField.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { TooltipProvider } from '@/components/ui/tooltip';
import { mapAdditionalFieldToDialogForm } from '@/domain/MasterData';
import type { AdditionalField, AdditionalFieldDialogFormState, AdditionalFieldPayload, AdditionalFieldType } from '@/domain/MasterData';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { normalizeChipDisplay, normalizeUniqueChipList } from '@/lib/textNormalizer';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface CategorySubGroupOption {
    id: number;
    name: string;
}

interface CatalogSourceOption {
    id: string;
    name: string;
}

const categorySubGroupOptions = ref<CategorySubGroupOption[]>([]);
const catalogSourceOptions = ref<CatalogSourceOption[]>([]);

async function fetchCategorySubGroupOptions() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.categorySubGroups, { params: { per_page: 1000 } });
        categorySubGroupOptions.value = (data.data ?? [])
            .map((sg: any) => ({ id: sg.id, name: sg.name }))
            .sort((a: CategorySubGroupOption, b: CategorySubGroupOption) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
    } catch (error) {
        console.error('Error al obtener los subgrupos de categoría:', error);
        categorySubGroupOptions.value = [];
    }
}

async function fetchCatalogSourceOptions() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.additionalFieldCatalogSources);
        catalogSourceOptions.value = (data.data ?? []).sort((a: CatalogSourceOption, b: CatalogSourceOption) =>
            a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }),
        );
    } catch (error) {
        console.error('Error al obtener los catálogos disponibles:', error);
        catalogSourceOptions.value = [];
    }
}

onMounted(() => {
    fetchCategorySubGroupOptions();
    fetchCatalogSourceOptions();
});

const fieldTypeOptions: Array<{ id: AdditionalFieldType; name: string }> = [
    { id: 'date', name: 'Fecha' },
    { id: 'select', name: 'Lista de opciones' },
    { id: 'number', name: 'Número' },
    { id: 'decimal', name: 'Número decimal' },
    { id: 'boolean', name: 'Si/No' },
    { id: 'text', name: 'Texto' },
];

const originOptions: Array<{ id: 'list' | 'catalog'; name: string }> = [
    { id: 'list', name: 'Lista escrita a mano' },
    { id: 'catalog', name: 'Catálogo del sistema' },
];

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: AdditionalField;
    loading?: boolean;
    deferContent?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: AdditionalFieldPayload): void;
    (e: 'cancel'): void;
    (e: 'loading-state', value: boolean): void;
}>();

const defaultForm: AdditionalFieldDialogFormState = {
    name: '',
    field_type: 'text',
    options: [],
    source_type: 'list',
    catalog_key: null,
    default_value: '',
    description: '',
    is_required: false,
    status: true,
    category_sub_group_ids: [],
};

const localForm = ref<AdditionalFieldDialogFormState>({
    ...defaultForm,
    ...mapAdditionalFieldToDialogForm(props.form),
});
const isInitializingDialog = ref(false);
const localOptions = ref<string[]>([]);
const formError = ref('');
const shouldEmitLoadingState = computed(() => props.open && isInitializingDialog.value);

const isSelectField = computed(() => localForm.value.field_type === 'select');
const isCatalogSourced = computed(() => isSelectField.value && localForm.value.source_type === 'catalog');
const defaultValueOptions = computed(() => {
    if (!isSelectField.value || isCatalogSourced.value) {
        return [];
    }

    return normalizeUniqueChipList(localOptions.value, undefined, 'title-case').map((value) => ({ id: value, name: value }));
});
const defaultValueInputType = computed((): 'text' | 'number' | 'date' => {
    if (localForm.value.field_type === 'number' || localForm.value.field_type === 'decimal') {
        return 'number';
    }

    if (localForm.value.field_type === 'date') {
        return 'date';
    }

    return 'text';
});

watch(
    [() => props.open, () => props.form],
    ([isOpen, formValue]) => {
        if (!isOpen) {
            return;
        }

        localForm.value = {
            ...defaultForm,
            ...mapAdditionalFieldToDialogForm(formValue),
        };

        const optionsFromProps = Array.isArray(localForm.value.options) ? localForm.value.options : [];
        localOptions.value = normalizeUniqueChipList(optionsFromProps, undefined, 'title-case');
        formError.value = '';
    },
    { immediate: true },
);

watch(
    () => localForm.value.field_type,
    (value) => {
        if (value !== 'select') {
            localOptions.value = [];
            localForm.value.source_type = 'list';
            localForm.value.catalog_key = null;
        }
    },
    { immediate: true },
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            isInitializingDialog.value = false;
            return;
        }

        isInitializingDialog.value = true;
        try {
            await nextTick();
        } finally {
            isInitializingDialog.value = false;
        }
    },
);

watch(
    shouldEmitLoadingState,
    (value) => {
        emit('loading-state', value);
    },
    { immediate: true },
);

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function buildPayload(preparedOptions: string[]): AdditionalFieldPayload {
    const label = normalizeChipDisplay(localForm.value.name);
    const existingKey = (props.form as Partial<AdditionalField> & { key?: string })?.key ?? '';
    const fallbackKey = existingKey || label;
    const generatedKey = slugify(fallbackKey || '');

    const payload: AdditionalFieldPayload = {
        id: props.form?.id,
        label,
        field_type: localForm.value.field_type,
        options: preparedOptions.length ? preparedOptions : undefined,
        source_type: isSelectField.value ? (localForm.value.source_type ?? 'list') : undefined,
        catalog_key: isCatalogSourced.value ? localForm.value.catalog_key : null,
        default_value: localForm.value.default_value ?? undefined,
        is_required: localForm.value.is_required,
        status: localForm.value.status,
        position: localForm.value.position ?? props.form?.position,
        category_sub_group_ids: localForm.value.category_sub_group_ids ?? [],
    };

    if (generatedKey) {
        payload.key = generatedKey;
    }

    return payload;
}

function handleSubmit() {
    const preparedOptions = isSelectField.value && !isCatalogSourced.value ? normalizeUniqueChipList(localOptions.value, undefined, 'title-case') : [];

    if (isSelectField.value && !isCatalogSourced.value && preparedOptions.length === 0) {
        formError.value = 'Debe agregar al menos una opción para los campos de tipo lista.';
        return;
    }

    if (isCatalogSourced.value && !localForm.value.catalog_key) {
        formError.value = 'Debe seleccionar el catálogo del que vendrán las opciones.';
        return;
    }

    formError.value = '';
    emit('submit', buildPayload(preparedOptions));
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            v-if="!props.deferContent"
            class="w-full max-w-2xl overflow-hidden p-0"
            content-class="flex min-h-0 flex-1 flex-col overflow-hidden p-0"
            :scrollable="false"
        >
            <TooltipProvider>
                <div class="relative flex min-h-0 flex-1 flex-col">
                    <FormDialogHeader class="px-5 pb-3 pr-12 pt-4">
                        <DialogTitle>
                            {{ dialogType === 'create' ? 'Crear campo adicional' : 'Editar campo adicional' }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                dialogType === 'create'
                                    ? 'Configura un campo adicional para capturar atributos específicos del ítem en el flujo de registro de productos.'
                                    : 'Actualiza las propiedades del campo adicional seleccionado para adaptarlo a los requerimientos del negocio.'
                            }}
                        </DialogDescription>
                    </FormDialogHeader>

                    <FormDialogBody style="scrollbar-width: thin">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <Label class="text-sm font-medium">
                                        Nombre <span class="text-red-500">*</span>
                                    </Label>
                                    <InputField v-model="localForm.name" label="" placeholder="Nombre del campo" required />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">
                                            Tipo de campo <span class="text-red-500">*</span>
                                        </Label>
                                        <HelpTooltip text="Selecciona el tipo de dato que debe capturar este campo." />
                                    </div>
                                    <SelectField
                                        v-model="localForm.field_type"
                                        :options="fieldTypeOptions"
                                        option-label="name"
                                        option-value="id"
                                        required
                                        class="md:!px-1"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium">Valor por defecto</p>
                                    <HelpTooltip text="Indica un valor que pueda usarse automáticamente si es el mismo para la mayoría de ítems." />
                                </div>
                                <div>
                                    <div v-if="isSelectField" class="space-y-3">
                                        <SelectField
                                            v-model="localForm.default_value"
                                            label=""
                                            :options="defaultValueOptions"
                                            :disabled="defaultValueOptions.length === 0"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Selecciona un valor"
                                        />
                                        <p v-if="defaultValueOptions.length === 0" class="text-xs text-muted-foreground">
                                            Agrega opciones para habilitar un valor por defecto
                                        </p>
                                    </div>
                                    <DatePickerField
                                        v-else-if="localForm.field_type === 'date'"
                                        v-model="localForm.default_value"
                                        label=""
                                        placeholder-text="Selecciona una fecha"
                                        clearable
                                        show-today
                                    />
                                    <InputField
                                        v-else
                                        v-model="localForm.default_value"
                                        :type="defaultValueInputType"
                                        label=""
                                        placeholder="Valor por defecto"
                                    />
                                </div>
                            </div>

                            <div v-if="isSelectField" class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Origen de las opciones</Label>
                                    <HelpTooltip
                                        text="Lista escrita a mano: tú defines los valores. Catálogo del sistema: las opciones se toman siempre actualizadas de otro catálogo (ej. nombres de tela)."
                                    />
                                </div>
                                <SelectField v-model="localForm.source_type" :options="originOptions" option-label="name" option-value="id" required />
                            </div>

                            <div v-if="isSelectField && !isCatalogSourced" class="space-y-2">
                                <ChipInputField
                                    v-model="localOptions"
                                    label="Valores"
                                    placeholder="Agregar valor"
                                    empty-text="Sin valores registrados."
                                    :required="true"
                                    case-mode="title-case"
                                />
                            </div>

                            <div v-if="isCatalogSourced" class="flex flex-col gap-1">
                                <Label class="text-sm font-medium">Catálogo</Label>
                                <SelectField
                                    v-model="localForm.catalog_key"
                                    :options="catalogSourceOptions"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Selecciona un catálogo"
                                    required
                                />
                                <p class="text-xs text-muted-foreground">
                                    Las opciones se resuelven en vivo desde este catálogo: un valor nuevo aparece sin volver a editar este campo.
                                </p>
                            </div>

                            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Categorías</Label>
                                    <HelpTooltip text="Restringe en qué subgrupos de categoría aparece este campo. Sin selección, el campo es global y aparece en todos los ítems." />
                                </div>
                                <SelectField
                                    v-model="localForm.category_sub_group_ids"
                                    label=""
                                    multiple
                                    :options="categorySubGroupOptions"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Global (todas las categorías)"
                                />
                            </div>

                            <div class="my-2 h-px bg-slate-100" />

                            <TextareaField data-no-uppercase label="Descripción" placeholder="Opcional" :rows="3" v-model="localForm.description" />

                            <div class="grid grid-cols-2 gap-5 pt-2">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">Obligatorio</span>
                                        <HelpTooltip text="Forzar la captura de este dato antes de guardar el ítem." />
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="localForm.is_required"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="localForm.is_required ? 'bg-primary' : 'bg-input'"
                                        @click="localForm.is_required = !localForm.is_required"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                                            :class="localForm.is_required ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">Activo</span>
                                        <HelpTooltip text="Habilita o pausa el campo sin eliminar su configuración." />
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="localForm.status"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="localForm.status ? 'bg-primary' : 'bg-input'"
                                        @click="localForm.status = !localForm.status"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                                            :class="localForm.status ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </FormDialogBody>

                    <FormDialogFooter class="justify-end gap-2 bg-background">
                        <Button variant="outline" size="pill" @click="handleClose">Cancelar</Button>
                        <button
                            type="button"
                            :disabled="loading"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="handleSubmit"
                        >
                            {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                        </button>
                    </FormDialogFooter>
                </div>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>

<style scoped>
.help-tooltip-content {
    background-color: #fff;
    border: 1px solid #cbd5f5;
    color: #0f172a;
    padding: 0.4rem 0.85rem;
    font-size: 0.85rem;
    border-radius: 0.75rem;
    box-shadow:
        0 20px 25px -5px rgba(15, 23, 42, 0.2),
        0 8px 10px -6px rgba(15, 23, 42, 0.1);
    position: relative;
}

.help-tooltip-content::after,
.help-tooltip-content::before {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 0.45rem solid transparent;
    border-right: 0.45rem solid transparent;
    bottom: -0.65rem;
}

.help-tooltip-content::after {
    border-top: 0.45rem solid #fff;
}

.help-tooltip-content::before {
    border-top: 0.45rem solid #cbd5f5;
    bottom: -0.72rem;
}

.switch-inline {
    position: relative;
}

.switch-inline label {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
