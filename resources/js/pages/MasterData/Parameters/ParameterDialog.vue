<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { mapParameterToDialogForm } from '@/domain/MasterData';
import type { ParameterDialogFormState } from '@/domain/MasterData';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

export type ParameterDialogModel = ParameterDialogFormState;

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit' | 'view';
    form: ParameterDialogModel | null;
    loading?: boolean;
    deferContent?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: ParameterDialogModel): void;
    (e: 'cancel'): void;
    (e: 'loading-state', value: boolean): void;
}>();

const defaultForm: ParameterDialogModel = {
    label: '',
    description: '',
    status: true,
    value: [],
};

const localForm = ref<ParameterDialogModel>({
    ...defaultForm,
    ...mapParameterToDialogForm(props.form),
});
const valueList = ref<string[]>([]);
const newValueInput = ref('');
const isInitializingDialog = ref(false);
// const hasValues = computed(() => valueList.value.length > 0);
const hasDuplicateOptions = computed(() => false);
const isViewMode = computed(() => props.dialogType === 'view');
const shouldEmitLoadingState = computed(() => props.open && isInitializingDialog.value);

watch(
    [() => props.open, () => props.form],
    ([isOpen, value]) => {
        if (!isOpen) {
            return;
        }

        if (!value) {
            localForm.value = { ...defaultForm };
            valueList.value = [];
            newValueInput.value = '';
            return;
        }

        localForm.value = mapParameterToDialogForm(value);
        valueList.value = parseValueList(localForm.value.value);
        newValueInput.value = '';
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
            if (props.dialogType === 'create' && !props.form) {
                localForm.value = { ...defaultForm };
            }
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

function parseValueList(value: unknown): string[] {
    if (!value) {
        return [];
    }

    if (Array.isArray(value)) {
        return value.map((entry) => displayEntry(entry)).filter((v): v is string => typeof v === 'string' && v.length > 0);
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed.startsWith('[')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    return parsed.map((entry) => displayEntry(entry)).filter((v): v is string => typeof v === 'string' && v.length > 0);
                }
            } catch {
                // ignore
            }
        }
        return [trimmed];
    }

    return [displayEntry(value)];
}

function displayEntry(entry: unknown) {
    if (entry === null || entry === undefined) {
        return '-';
    }
    if (typeof entry === 'object') {
        return (
            (entry as Record<string, unknown>).name?.toString() ??
            (entry as Record<string, unknown>).label?.toString() ??
            ((entry as Record<string, unknown>).id ? String((entry as Record<string, unknown>).id) : undefined) ??
            JSON.stringify(entry)
        );
    }
    return String(entry);
}

function addValueEntry() {
    const trimmed = newValueInput.value.trim();
    if (!trimmed) {
        return;
    }

    valueList.value = [...valueList.value, trimmed];
    newValueInput.value = '';
}

function removeValueEntry(index: number) {
    valueList.value = valueList.value.filter((_, i) => i !== index);
}

function buildPayloadValue() {
    return valueList.value.map((entry) => ({ name: entry }));
}

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    emit('submit', {
        ...localForm.value,
        value: buildPayloadValue(),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            v-if="!props.deferContent"
            class="flex h-[95vh] max-h-[95vh] w-full max-w-2xl flex-col overflow-hidden p-0"
            content-class="flex min-h-0 flex-1 flex-col overflow-hidden p-0"
            :scrollable="false"
        >
            <div class="relative flex min-h-0 flex-1 flex-col">
                <FormDialogHeader class="px-5 pb-3 pr-12 pt-4">
                    <DialogTitle>
                        {{ dialogType === 'create' ? 'Crear parámetro' : dialogType === 'edit' ? 'Editar parámetro' : 'Ver parámetro' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            dialogType === 'create'
                                ? 'Define un valor global que el sistema podrá reutilizar en distintos módulos.'
                                : dialogType === 'edit'
                                  ? 'Actualiza las propiedades del parámetro seleccionado.'
                                  : 'Visualiza los valores almacenados del parámetro sin editar.'
                        }}
                    </DialogDescription>
                </FormDialogHeader>

                <FormDialogBody style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <InputField
                            v-model="localForm.label"
                            label="Nombre"
                            placeholder="Nombre visible"
                            required
                            :disabled="isViewMode"
                        />
                        <div class="space-y-2">
                            <p class="text-sm font-medium">
                                Valores <span class="text-destructive">*</span>
                            </p>
                            <div v-if="!isViewMode" class="flex gap-2">
                                <InputField
                                    v-model="newValueInput"
                                    label=""
                                    placeholder="Agregar valor"
                                    @keydown.enter.prevent="addValueEntry()"
                                />
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                                    @click="addValueEntry"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="valueList.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="(item, index) in valueList"
                                    :key="`value-chip-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-foreground"
                                >
                                    {{ item }}
                                    <button
                                        v-if="!isViewMode"
                                        type="button"
                                        class="text-muted-foreground transition-colors hover:text-foreground"
                                        @click="removeValueEntry(index)"
                                        aria-label="Eliminar valor"
                                    >×</button>
                                </span>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Sin valores registrados.</p>
                        </div>
                        <TextareaField
                            v-model="localForm.description"
                            label="Descripción"
                            placeholder="Opcional"
                            :rows="3"
                            :disabled="isViewMode"
                        />
                        <div class="flex w-full items-center gap-3 text-sm text-foreground/80">
                            <span>Activo</span>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="localForm.status"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                :class="localForm.status ? 'bg-primary' : 'bg-input'"
                                :disabled="isViewMode"
                                @click="!isViewMode && (localForm.status = !localForm.status)"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                                    :class="localForm.status ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </div>
                    </div>
                </FormDialogBody>

                <FormDialogFooter class="justify-end gap-2 bg-background">
                    <Button variant="outline" size="pill" @click="handleClose">Cancelar</Button>
                    <button
                        v-if="!isViewMode"
                        type="button"
                        :disabled="loading || hasDuplicateOptions"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleSubmit"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </button>
                </FormDialogFooter>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
