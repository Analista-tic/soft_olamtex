<script setup lang="ts">
import TableStatusBadge, { type TableStatusTone } from '@/components/common/TableStatusBadge.vue';
import { computed } from 'vue';

const props = defineProps<{
    text?: string;
    type?: string;
    status?: boolean | string | number;
    title?: string;
}>();

const normalizedTone = computed<TableStatusTone>(() => {
    if (props.type) return props.type as TableStatusTone;

    const value = String(props.status).toLowerCase();

    if (['1', 'true', 'habilitado', 'activo', 'ok', 'completo', 'approved'].includes(value)) return 'green';
    if (['0', 'false', 'deshabilitado', 'inactivo', 'error', 'rejected', 'cancelled'].includes(value)) return 'red';
    if (['pendiente', 'warning', 'amarillo', 'atencion', 'incomplete'].includes(value)) return 'yellow';
    if (['info', 'azul', 'blue', 'enviado', 'sent'].includes(value)) return 'blue';

    return 'gray';
});

const autoText = computed(() => {
    if (props.text) return props.text;

    const value = props.status;
    if (value === true || value === 1) return 'Habilitado';
    if (value === false || value === 0) return 'Deshabilitado';
    return String(value).charAt(0).toUpperCase() + String(value).slice(1);
});
</script>

<template>
    <TableStatusBadge :label="autoText" :tone="normalizedTone" :title="props.title">
        <slot>{{ autoText }}</slot>
    </TableStatusBadge>
</template>
