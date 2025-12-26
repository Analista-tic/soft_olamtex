<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import FormHeader from '@/components/FormHeader.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import type { FabricRollView } from '@/types';

type DownloadForm = {
    adjustmentType: string;
    quantity: string;
    productionOrder: string;
    creditNote: string;
    reason: string;
};

const props = defineProps<{ roll?: FabricRollView | null }>();

const adjustmentTypeOptions = [
    { id: 'salida', name: 'Salida' },
    { id: 'entrada', name: 'Entrada' },
];

const form = reactive<DownloadForm>({
    adjustmentType: '',
    quantity: '',
    productionOrder: '',
    creditNote: '',
    reason: '',
});

const successMessage = ref('');
const errorMessage = ref('');
const isSubmitting = ref(false);

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
    { title: 'Descarga por rollo' },
];

const rollDetails = computed(() => {
    if (!props.roll) {
        return [];
    }

    return [
        { label: 'Rollo', value: props.roll.roll },
        { label: 'Tela', value: props.roll.fabric },
        { label: 'Color', value: props.roll.color },
        { label: 'Proveedor', value: props.roll.provider },
        { label: 'Pedido', value: props.roll.orderNumber },
    ];
});

function resetForm() {
    form.adjustmentType = '';
    form.quantity = '';
    form.productionOrder = '';
    form.creditNote = '';
    form.reason = '';
}

function submitDownload() {
    isSubmitting.value = true;
    const adjustmentLabel = form.adjustmentType || 'descarga';
    const quantityLabel = form.quantity ? ` de ${form.quantity}` : '';
    const rollLabel = props.roll ? ` (${props.roll.roll})` : '';
    successMessage.value = `Solicitud de ${adjustmentLabel}${quantityLabel}${rollLabel} registrada.`;
    errorMessage.value = '';
    isSubmitting.value = false;
    resetForm();
}
</script>

<template>
    <Head title="Descarga por rollo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <FormHeader
                title="Descarga por rollo"
                description="Registra los datos de la salida antes de procesarla."
            />

            <div v-if="props.roll" class="rounded-2xl border border-border bg-white p-4 shadow-sm">
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <div
                        v-for="detail in rollDetails"
                        :key="detail.label"
                        class="rounded-xl border border-dashed border-border/70 px-3 py-2 text-sm"
                    >
                        <dt class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ detail.label }}</dt>
                        <dd class="text-base font-medium text-foreground">{{ detail.value }}</dd>
                    </div>
                </dl>
            </div>

            <form class="space-y-6" @submit.prevent="submitDownload">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Tipo de ajuste</p>
                        <SelectField
                            v-model="form.adjustmentType"
                            :options="adjustmentTypeOptions"
                            label=""
                            placeholder="Selecciona tipo"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Cantidad a descargar</p>
                        <Input v-model="form.quantity" placeholder="0 m" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Orden de produccion</p>
                        <Input v-model="form.productionOrder" placeholder="OP-0001" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground"># Nota Credito</p>
                        <Input v-model="form.creditNote" placeholder="NC-1234" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Motivo</p>
                        <Input v-model="form.reason" placeholder="Descripcion del motivo" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="outline" type="button" class="w-full sm:w-auto" @click="resetForm">
                        Limpiar
                    </Button>
                    <Button variant="default" type="submit" class="w-full sm:w-auto" :disabled="isSubmitting">
                        Registrar descarga
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
