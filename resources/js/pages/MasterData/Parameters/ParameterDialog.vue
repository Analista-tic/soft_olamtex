<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import InputField from '@/components/InputField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

export type ParameterDialogModel = {
    id?: number | string;
    key?: string;
    label: string;
    description?: string;
    value: unknown;
    status?: boolean;
};

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

const defaultForm = { label: '', description: '', status: true, value: [] } as ParameterDialogModel;

const localForm = ref({ ...defaultForm });
const valueList = ref<string[]>([]);
const newValueInput = ref('');
const isInitializingDialog = ref(false);

const isViewMode = computed(() => props.dialogType === 'view');
const shouldEmitLoadingState = computed(() => props.open && isInitializingDialog.value);

/** Determina si el valor es una lista editable con chips o un valor escalar. */
const isList = computed(() => {
    const v = localForm.value.value;
    if (Array.isArray(v)) return true;
    if (typeof v === 'string') {
        const t = v.trim();
        if (t.startsWith('[')) {
            try { JSON.parse(t); return true; } catch { /* not json */ }
        }
    }
    return false;
});

watch(
    () => props.form,
    (value) => {
        if (!value) {
            localForm.value = { ...defaultForm };
            valueList.value = [];
            newValueInput.value = '';
            return;
        }
        localForm.value = {
            label: value.label ?? '',
            description: value.description ?? '',
            status: value.status ?? true,
            value: value.value ?? [],
        };
        valueList.value = parseValueList(value.value);
        newValueInput.value = '';
    },
    { immediate: true },
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) { isInitializingDialog.value = false; return; }
        isInitializingDialog.value = true;
        try {
            await nextTick();
            if (props.dialogType === 'create' && !props.form) {
                localForm.value = { ...defaultForm };
                valueList.value = [];
            }
        } finally {
            isInitializingDialog.value = false;
        }
    },
);

watch(shouldEmitLoadingState, (value) => { emit('loading-state', value); }, { immediate: true });

function parseValueList(value: unknown): string[] {
    if (!value) return [];
    if (Array.isArray(value)) {
        return value.map(entryToString).filter((v): v is string => Boolean(v));
    }
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed.startsWith('[')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) return parsed.map(entryToString).filter((v): v is string => Boolean(v));
            } catch { /* ignore */ }
        }
        return trimmed ? [trimmed] : [];
    }
    const s = entryToString(value);
    return s ? [s] : [];
}

function entryToString(entry: unknown): string {
    if (entry === null || entry === undefined) return '';
    if (typeof entry === 'object') {
        return (
            (entry as Record<string, unknown>).name?.toString() ??
            (entry as Record<string, unknown>).label?.toString() ??
            ((entry as Record<string, unknown>).id ? String((entry as Record<string, unknown>).id) : undefined) ??
            ''
        );
    }
    return String(entry);
}

function addValueEntry() {
    const trimmed = newValueInput.value.trim();
    if (!trimmed) return;
    if (!valueList.value.includes(trimmed)) {
        valueList.value = [...valueList.value, trimmed];
    }
    newValueInput.value = '';
}

function removeValueEntry(index: number) {
    valueList.value = valueList.value.filter((_, i) => i !== index);
}

function buildPayloadValue(): unknown {
    if (isList.value) {
        return valueList.value.map((entry) => ({ name: entry }));
    }
    return localForm.value.value;
}

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    emit('submit', { ...localForm.value, value: buildPayloadValue() });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent v-if="!props.deferContent" class="flex max-h-[90vh] w-full max-w-2xl flex-col gap-0 p-0">

            <!-- Header -->
            <div class="border-b border-sidebar-border/70 bg-muted/20 px-5 py-3 dark:border-sidebar-border">
                <FormHeader
                    :title="dialogType === 'create' ? 'Crear parámetro' : dialogType === 'edit' ? 'Editar parámetro' : 'Ver parámetro'"
                    :description="
                        dialogType === 'create'
                            ? 'Define una lista de opciones reutilizable en distintos módulos.'
                            : dialogType === 'edit'
                              ? 'Actualiza las opciones del parámetro seleccionado.'
                              : 'Opciones registradas en este parámetro.'
                    "
                />
            </div>

            <!-- Body -->
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">

                <InputField
                    v-model="localForm.label"
                    label="Nombre"
                    placeholder="Nombre visible del parámetro"
                    required
                    :disabled="isViewMode"
                />

                <!-- Lista de opciones -->
                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        Opciones
                        <span v-if="!isViewMode" class="text-destructive">*</span>
                        <span class="ml-2 text-[10px] font-normal text-muted-foreground">
                            {{ valueList.length }} {{ valueList.length === 1 ? 'opción' : 'opciones' }}
                        </span>
                    </p>

                    <!-- Entrada para agregar (solo edición) -->
                    <div v-if="!isViewMode" class="flex gap-2">
                        <InputField
                            v-model="newValueInput"
                            label=""
                            placeholder="Escribe una opción y presiona Enter o +"
                            @keydown.enter.prevent="addValueEntry()"
                        />
                        <button
                            type="button"
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                            @click="addValueEntry"
                        >
                            <Plus class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Chips -->
                    <div v-if="valueList.length" class="flex flex-wrap gap-2 rounded-lg border bg-muted/20 p-2.5">
                        <span
                            v-for="(item, index) in valueList"
                            :key="`chip-${index}`"
                            class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-foreground"
                        >
                            {{ item }}
                            <button
                                v-if="!isViewMode"
                                type="button"
                                class="flex h-3.5 w-3.5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-destructive/20 hover:text-destructive"
                                aria-label="Eliminar opción"
                                @click="removeValueEntry(index)"
                            >×</button>
                        </span>
                    </div>
                    <p v-else class="text-xs text-muted-foreground italic">Sin opciones registradas.</p>
                </div>

                <TextareaField
                    v-model="localForm.description"
                    label="Descripción"
                    placeholder="Opcional — explica para qué se usa este parámetro"
                    :rows="2"
                    :disabled="isViewMode"
                />

                <!-- Estado -->
                <div
                    class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors"
                    :class="localForm.status ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-800 dark:bg-emerald-950/20' : 'border-border bg-muted/20'"
                >
                    <span class="flex-1 text-foreground/80">{{ localForm.status ? 'Parámetro activo' : 'Parámetro inactivo' }}</span>
                    <SwitchField v-model="localForm.status!" :disabled="isViewMode" />
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="border-t px-5 py-3">
                <Button variant="outline" size="pill" @click="handleClose">Cancelar</Button>
                <button
                    v-if="!isViewMode"
                    type="button"
                    :disabled="loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleSubmit"
                >
                    {{ loading ? 'Guardando…' : dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
