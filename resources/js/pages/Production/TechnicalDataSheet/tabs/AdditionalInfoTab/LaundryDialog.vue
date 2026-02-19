<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import SelectField from '@/components/SelectField.vue';
import { Textarea } from '@/components/ui/textarea';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import type { Option } from '@/domain/shared';

type LaundryData = {
    type?: string | null;
    instructions?: string | null;
};

const LAUNDRY_TYPE_OPTIONS: Option[] = [
    { id: 'NO_APLICA', name: 'No aplica' },
    { id: 'ENZIMATICO', name: 'Enzimatico' },
    { id: 'STONE_WASH', name: 'Stone wash' },
    { id: 'SUAVIZADO', name: 'Suavizado' },
    { id: 'RINSE', name: 'Rinse' },
    { id: 'TENIDO', name: 'Tenido' },
    { id: 'OTRO', name: 'Otro' },
];

function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

const props = defineProps<{
    open: boolean;
    data: LaundryData;
    savingSection: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:data', value: LaundryData): void;
    (e: 'save', section: 'laundry'): void;
}>();

const attemptedSave = ref(false);

const localData = computed({
    get: () => props.data,
    set: (value: LaundryData) => emit('update:data', value),
});

const isLaundryTypeInvalid = computed(() => {
    if (!attemptedSave.value) return false;
    return !String(localData.value.type ?? '').trim();
});

function updateLaundryType(value: string | null): void {
    emit('update:data', {
        ...localData.value,
        type: normalizeText(value),
    });
}

function updateInstructions(value: string): void {
    emit('update:data', {
        ...localData.value,
        instructions: normalizeText(value) || value,
    });
}

function handleSave(): void {
    attemptedSave.value = true;
    if (isLaundryTypeInvalid.value) return;
    emit('save', 'laundry');
}

function handleClose(): void {
    attemptedSave.value = false;
    emit('close');
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) handleClose();
}
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent class="flex max-h-[85vh] max-w-[700px] flex-col px-2 py-2">
            <FormHeader title="Lavanderia" description="Indicaciones del proceso de lavado y acabado." />

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1 pt-4">
                <div class="rounded-lg border border-border bg-background p-4">
                    <div class="mb-3">
                        <p class="text-sm font-semibold text-foreground">Instrucciones de lavanderia</p>
                        <p class="text-xs text-muted-foreground">Indicaciones del proceso de lavado/acabado.</p>
                    </div>

                    <div class="mb-3 max-w-sm space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tipo de lavado</p>
                        <SelectField
                            :model-value="localData.type"
                            :options="LAUNDRY_TYPE_OPTIONS"
                            :searchable="false"
                            placeholder="Selecciona tipo de lavado"
                            :invalid="isLaundryTypeInvalid"
                            @update:model-value="(value) => updateLaundryType(value as string | null)"
                        />
                        <p v-if="isLaundryTypeInvalid" class="text-xs text-destructive">Selecciona un tipo de lavado para guardar.</p>
                    </div>

                    <Textarea
                        :model-value="localData.instructions ?? ''"
                        rows="8"
                        placeholder="Escribe instrucciones de lavanderia"
                        @update:model-value="(value) => updateInstructions(String(value))"
                    />
                </div>
            </div>

            <DialogFooter class="mt-6 justify-end gap-2">
                <Button type="button" variant="outline" @click="handleClose">Cancelar</Button>
                <Button type="button" :disabled="savingSection === 'laundry'" @click="handleSave">
                    {{ savingSection === 'laundry' ? 'Guardando...' : 'Guardar lavanderia' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
