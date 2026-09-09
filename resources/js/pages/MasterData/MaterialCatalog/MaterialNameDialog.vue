<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { computed, ref, watch } from 'vue';

export type MaterialNameKind = 'fabric' | 'yarn';

export type MaterialNameForm = {
    id: number | null;
    nombre: string;
    base_id: number | string | null;
    is_active: boolean;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        kind: MaterialNameKind;
        form: MaterialNameForm;
        baseOptions: { id: number; name: string }[];
        loading?: boolean;
        errorMessage?: string;
    }>(),
    { loading: false, errorMessage: '' },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', form: MaterialNameForm): void;
    (e: 'cancel'): void;
}>();

const internalForm = ref<MaterialNameForm>({ ...props.form });

watch(
    () => props.form,
    (val) => {
        internalForm.value = { ...val };
    },
    { deep: true },
);

const isEditMode = computed(() => internalForm.value.id !== null);
const kindLabel = computed(() => (props.kind === 'fabric' ? 'tela' : 'hilaza'));
const baseSelectOptions = computed(() => props.baseOptions.map((option) => ({ id: String(option.id), name: option.name })));

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
                <DialogTitle>{{ isEditMode ? 'Editar' : 'Nuevo' }} nombre de {{ kindLabel }}</DialogTitle>
                <DialogDescription>
                    El nombre específico debe pertenecer a una base de {{ kindLabel }} ya registrada — la base define el
                    <span v-if="props.kind === 'fabric'">factor que afecta el SAM.</span>
                    <span v-else>catálogo operativo.</span>
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 px-6 pb-4" @submit.prevent="submit">
                <InputField v-model="internalForm.nombre" label="Nombre" placeholder="Ej: JERSEY 24" required />

                <div class="flex flex-col gap-1">
                    <SelectField
                        v-model="internalForm.base_id"
                        :label="`Base de ${kindLabel}`"
                        :options="baseSelectOptions"
                        placeholder="Selecciona una base"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        ¿No existe la base que necesitas? Créala primero en la sección "Bases de {{ kindLabel }}" de
                        esta misma pantalla.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="material-name-active" v-model:checked="internalForm.is_active" />
                    <Label for="material-name-active">Activo</Label>
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
