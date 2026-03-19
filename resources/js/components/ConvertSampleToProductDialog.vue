<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    open: boolean;
    loading?: boolean;
    initialValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    initialValue: '',
});

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'confirm', productReference: string): void;
}>();

const internalOpen = ref(props.open);
const productReference = ref(props.initialValue);
const errorMessage = ref('');

watch(
    () => props.open,
    (value) => {
        internalOpen.value = value;
        if (value) {
            productReference.value = props.initialValue ?? '';
            errorMessage.value = '';
        }
    },
);

watch(
    () => props.initialValue,
    (value) => {
        if (internalOpen.value) {
            productReference.value = value ?? '';
        }
    },
);

watch(
    () => internalOpen.value,
    (value) => emit('update:open', value),
);

const isDisabled = computed(() => Boolean(props.loading));

function closeDialog() {
    internalOpen.value = false;
}

function confirmDialog() {
    const normalized = (productReference.value ?? '').trim();
    if (!normalized) {
        errorMessage.value = 'Referencia de producto es requerida.';
        return;
    }
    errorMessage.value = '';
    emit('confirm', normalized);
}
</script>

<template>
    <Dialog v-model:open="internalOpen" @close="closeDialog">
        <DialogContent class="max-w-md border border-border p-8">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Convertir muestra a producto</DialogTitle>
                <DialogDescription class="pb-2"> Ingresa la nueva referencia de producto. </DialogDescription>
            </DialogHeader>

            <InputField
                v-model="productReference"
                label="Referencia producto"
                placeholder="Ej: PRD-1234"
                :disabled="isDisabled"
                :error="errorMessage"
            />

            <DialogFooter class="flex justify-end gap-2">
                <Button
                    variant="outline"
                    size="pill"
                    type="button"
                    :disabled="isDisabled"
                    @click="closeDialog"
                >
                    Cancelar
                </Button>
                <Button
                    variant="default"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isDisabled"
                    @click="confirmDialog"
                >
                    <Loader2 v-if="props.loading" class="mr-2 h-4 w-4 animate-spin" />
                    {{ props.loading ? 'Convirtiendo...' : 'Convertir' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
