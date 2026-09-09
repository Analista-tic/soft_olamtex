<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { CategoryGroup } from '@/domain/MasterData';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    groupData?: Partial<CategoryGroup>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', group: CategoryGroup): void;
}>();

const internalIsOpen = ref(props.isOpen);
const isSaving = ref(false);
const errorMessage = ref('');
const form = ref<Partial<CategoryGroup>>({ name: '', code: '' });

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
        if (newValue) {
            form.value = { name: props.groupData?.name ?? '', code: props.groupData?.code ?? '' };
            errorMessage.value = '';
        }
    },
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) emit('close');
    },
);

function closeDialog() {
    internalIsOpen.value = false;
}

async function submitForm() {
    errorMessage.value = '';
    isSaving.value = true;
    try {
        const payload = { name: form.value.name, code: form.value.code || null };
        const response =
            props.dialogType === 'create'
                ? await axios.post<CategoryGroup>('/categorias/grupos', payload)
                : await axios.put<CategoryGroup>(`/categorias/grupos/${props.groupData?.id}`, payload);
        emit('saved', response.data);
        closeDialog();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const validationErrors = axiosErr?.response?.data?.errors;
        errorMessage.value = validationErrors
            ? Object.values(validationErrors).flat().join('\n')
            : (axiosErr?.response?.data?.message ?? 'No se pudo guardar el grupo.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex max-w-md flex-col px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>{{ dialogType === 'create' ? 'Crear grupo' : 'Editar grupo' }}</DialogTitle>
                    <DialogDescription>
                        {{ dialogType === 'create' ? 'Completa los campos para crear un nuevo grupo.' : 'Actualiza el nombre o el código del grupo.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <InputField v-model="form.name" label="Nombre" placeholder="Nombre del grupo" required autocomplete="off" />
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Código</Label>
                                <HelpTooltip
                                    text="2 dígitos, único. Se usa para componer la referencia automática de los ítems de este grupo (consecutivo+grupo+subgrupo+color). Déjalo vacío si aún no lo necesitas."
                                    aria-label="Ayuda sobre el código"
                                />
                            </div>
                            <InputField v-model="form.code" label="" placeholder="Ej: 01" maxlength="2" />
                        </div>
                        <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                    </div>
                </div>
                <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                    <Button variant="outline" size="pill" :disabled="isSaving" @click="closeDialog">Cancelar</Button>
                    <Button type="button" size="pill" :disabled="isSaving" @click="submitForm">
                        {{ isSaving ? 'Guardando...' : dialogType === 'create' ? 'Crear' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>
