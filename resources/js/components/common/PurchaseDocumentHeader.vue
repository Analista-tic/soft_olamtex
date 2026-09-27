<script setup lang="ts">
/**
 * Encabezado de solo lectura de un documento de compra (orden, factura, recepción).
 * Fila 1: Proveedor / N° identificación / Contacto.
 * Fila 2: Fecha / Fecha de entrega / N° de documento del proveedor.
 * El slot por defecto añade campos extra (p. ej. Estado) a la misma rejilla.
 */
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        providerName?: string | null;
        idNumber?: string | null;
        phone?: string | null;
        email?: string | null;
        /** Fecha ya formateada para mostrar. */
        date?: string | null;
        /** Fecha de entrega ya formateada para mostrar. */
        deliveryDate?: string | null;
        documentNumber?: string | null;
        dateLabel?: string;
        deliveryDateLabel?: string;
        documentNumberLabel?: string;
        /** Envuelve la rejilla en una tarjeta con borde. */
        card?: boolean;
    }>(),
    {
        dateLabel: 'Fecha',
        deliveryDateLabel: 'Fecha de entrega',
        documentNumberLabel: 'N° documento proveedor',
        card: true,
    },
);

const dash = (v?: string | null) => (v && String(v).trim() !== '' ? v : '-');
const hasEmail = computed(() => Boolean(props.email));
</script>

<template>
    <div :class="card ? 'rounded-xl border border-border bg-card p-4' : ''">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-3">
            <div>
                <dt class="text-xs text-muted-foreground">Proveedor</dt>
                <dd class="text-sm font-semibold">{{ dash(providerName) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Identificación</dt>
                <dd class="text-sm">{{ dash(idNumber) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Contacto</dt>
                <dd class="text-sm">
                    {{ dash(phone) }}
                    <template v-if="hasEmail"> · {{ email }}</template>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">{{ dateLabel }}</dt>
                <dd class="text-sm">{{ dash(date) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">{{ deliveryDateLabel }}</dt>
                <dd class="text-sm">{{ dash(deliveryDate) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">{{ documentNumberLabel }}</dt>
                <dd class="text-sm">{{ dash(documentNumber) }}</dd>
            </div>
            <slot />
        </dl>
    </div>
</template>
