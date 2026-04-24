<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

type PaymentSummary = {
    invoice_total: number;
    credit_notes_total: number;
    debit_notes_total: number;
    balance_after_notes: number;
    payments_total: number;
    current_balance: number;
};

const props = defineProps<{
    open: boolean;
    invoiceLabel: string;
    invoiceSupplier: string;
    paymentCurrentBalance: number;
    paymentSummary: PaymentSummary | null;
    paymentDate: string | null;
    paymentAmountInput: string;
    paymentReference: string;
    paymentNotes: string;
    paymentSubmitting: boolean;
    paymentMessage: string | null;
    paymentError: string | null;
    canRegisterPayment: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit'): void;
    (e: 'update:paymentDate', value: string | null): void;
    (e: 'update:paymentAmountInput', value: string): void;
    (e: 'update:paymentReference', value: string): void;
    (e: 'update:paymentNotes', value: string): void;
}>();

const paymentDateModel = computed({
    get: () => props.paymentDate,
    set: (value: string | null) => emit('update:paymentDate', value),
});

const paymentAmountInputModel = computed({
    get: () => props.paymentAmountInput,
    set: (value: string) => emit('update:paymentAmountInput', value),
});

const paymentReferenceModel = computed({
    get: () => props.paymentReference,
    set: (value: string) => emit('update:paymentReference', value),
});

const paymentNotesModel = computed({
    get: () => props.paymentNotes,
    set: (value: string) => emit('update:paymentNotes', value),
});

const paymentAmount = computed(() => {
    const parsed = Number(props.paymentAmountInput);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
});

const projectedBalance = computed(() => Number(props.paymentCurrentBalance || 0) - paymentAmount.value);

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (!value ? emit('close') : null)">
        <DialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle :class="uiPatterns.typography.dialogTitle">Registrar pago</DialogTitle>
                <DialogDescription :class="uiPatterns.typography.dialogSubtitle">
                    {{ invoiceLabel }} - {{ invoiceSupplier || '-' }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                        <p :class="uiPatterns.typography.metricLabel">Saldo pendiente</p>
                        <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(paymentCurrentBalance) }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                        <p :class="uiPatterns.typography.metricLabel">Pagos aplicados</p>
                        <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(Number(paymentSummary?.payments_total || 0)) }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                        <p :class="uiPatterns.typography.metricLabel">Saldo con notas</p>
                        <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(Number(paymentSummary?.balance_after_notes || 0)) }}</p>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Fecha de pago</p>
                        <InputDate v-model="paymentDateModel" placeholder="Fecha de pago" />
                    </div>
                    <div>
                        <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Monto</p>
                        <Input
                            v-model="paymentAmountInputModel"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="Monto del pago"
                        />
                        <p :class="['mt-1', uiPatterns.typography.helperText]">
                            Pago actual: {{ formatCurrency(paymentAmount) }} - Saldo proyectado: {{ formatCurrency(projectedBalance) }}
                        </p>
                    </div>
                    <div>
                        <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Comprobante o referencia</p>
                        <Input v-model="paymentReferenceModel" placeholder="Comprobante / referencia" />
                    </div>
                    <div class="md:col-span-2">
                        <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Observacion</p>
                        <Textarea v-model="paymentNotesModel" rows="3" placeholder="Observaciones del pago" />
                    </div>
                </div>

                <div v-if="paymentError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ paymentError }}
                </div>
                <div v-if="paymentMessage" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ paymentMessage }}
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button type="button" variant="outline" size="pill" @click="emit('close')">
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="!canRegisterPayment"
                    @click="emit('submit')"
                >
                    <Loader2 v-if="paymentSubmitting" class="mr-1 h-4 w-4 animate-spin" />
                    Guardar pago
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
