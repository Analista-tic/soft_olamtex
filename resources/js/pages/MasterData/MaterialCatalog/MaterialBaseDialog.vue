<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { computed, ref, watch } from 'vue';

export type MaterialBaseKind = 'fabric' | 'yarn';

export type MaterialBaseForm = {
    id: number | null;
    nombre: string;
    factor: number | null;
    is_active: boolean;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        kind: MaterialBaseKind;
        form: MaterialBaseForm;
        canSeeFactor: boolean;
        loading?: boolean;
        errorMessage?: string;
    }>(),
    { loading: false, errorMessage: '' },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', form: MaterialBaseForm): void;
    (e: 'cancel'): void;
}>();

const internalForm = ref<MaterialBaseForm>({ ...props.form });

watch(
    () => props.form,
    (val) => {
        internalForm.value = { ...val };
    },
    { deep: true },
);

const isEditMode = computed(() => internalForm.value.id !== null);
const kindLabel = computed(() => (props.kind === 'fabric' ? 'tela' : 'hilaza'));
// Solo la base de tela afecta el SAM; hilaza no tiene columna factor en su tabla.
const showFactor = computed(() => props.kind === 'fabric' && props.canSeeFactor);

function cancel() {
    emit('cancel');
    emit('update:open', false);
}

function submit() {
    emit('submit', { ...internalForm.value });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="max-w-md">
            <AlertStack :error-message="props.errorMessage" error-type="error" @close-error="$emit('cancel')" />
            <DialogHeader class="px-6 pb-4 pt-6">
                <DialogTitle>{{ isEditMode ? 'Editar' : 'Nueva' }} base de {{ kindLabel }}</DialogTitle>
                <DialogDescription>
                    Categoría amplia de composición (ej. "ALGODÓN", "POLIÉSTER").
                    <span v-if="props.kind === 'fabric'">Su factor afecta el cálculo del SAM en toda la producción.</span>
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 px-6 pb-4" @submit.prevent="submit">
                <InputField v-model="internalForm.nombre" label="Nombre" placeholder="Ej: ALGODÓN" required />

                <div v-if="showFactor" class="flex flex-col gap-1">
                    <Label for="material-base-factor">Factor</Label>
                    <InputField id="material-base-factor" v-model="internalForm.factor" type="number" step="0.01" min="0.01" label="" />
                    <p class="text-xs text-muted-foreground">1.0 = neutro. 1.15 = +15% al SAM.</p>
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="material-base-active" v-model:checked="internalForm.is_active" />
                    <Label for="material-base-active">Activo</Label>
                </div>
            </form>

            <DialogFooter class="px-6 pb-6">
                <Button variant="outline" :disabled="props.loading" @click="cancel">Cancelar</Button>
                <Button :disabled="props.loading" @click="submit">
                    {{ props.loading ? 'Guardando...' : isEditMode ? 'Actualizar' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
