<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
    type?: 'success' | 'error' | 'info' | 'warning';
    title?: string;
    message: string;
    autoCloseMs?: number;
    closeable?: boolean; // Si es true, muestra botón X (default: false para tipos que se auto-cierran)
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const typeConfig: Record<string, { icon: any; colors: string; bgColor: string; borderColor: string; textColor: string; titleColor: string }> = {
    success: {
        icon: CheckCircle2,
        colors: 'text-green-600',
        bgColor: 'bg-green-50',
        borderColor: 'border-green-200',
        textColor: 'text-green-700',
        titleColor: 'text-green-800 font-semibold',
    },
    error: {
        icon: XCircle,
        colors: 'text-red-600',
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200',
        textColor: 'text-red-700',
        titleColor: 'text-red-800 font-semibold',
    },
    info: {
        icon: Info,
        colors: 'text-blue-600',
        bgColor: 'bg-blue-50',
        borderColor: 'border-blue-200',
        textColor: 'text-blue-700',
        titleColor: 'text-blue-800 font-semibold',
    },
    warning: {
        icon: AlertTriangle,
        colors: 'text-amber-600',
        bgColor: 'bg-amber-50',
        borderColor: 'border-amber-200',
        textColor: 'text-amber-700',
        titleColor: 'text-amber-800 font-semibold',
    },
};

const config = computed(() => typeConfig[props.type ?? 'info'] ?? typeConfig.info);
const IconComponent = computed(() => config.value.icon);

const defaultTitles: Record<string, string> = {
    success: 'Operación exitosa',
    error: 'Error',
    info: 'Información',
    warning: 'Advertencia',
};

// Determinar si mostrar botón cerrar
// Solo mostrar si explícitamente se pasa closeable=true o si es error (que no se auto-cierra)
const showCloseButton = computed(() => {
    if (props.closeable === true) return true;
    if (props.type === 'error' && (props.autoCloseMs === undefined || props.autoCloseMs === 0)) return true;
    return false;
});

let timeoutId: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.message,
    (val) => {
        if (!val) return;
        if (timeoutId) clearTimeout(timeoutId);

        const ms = props.autoCloseMs ?? 4000;
        if (ms > 0) {
            timeoutId = setTimeout(() => emit('close'), ms);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (timeoutId) clearTimeout(timeoutId);
});
</script>

<template>
    <div
        class="flex max-w-md gap-4 rounded-lg border px-4 py-3 shadow-md transition-all duration-300 ease-out animate-in fade-in slide-in-from-top-2"
        :class="`${config.bgColor} ${config.borderColor}`"
    >
        <div class="flex flex-shrink-0 items-start pt-0.5">
            <component :is="IconComponent" :class="`${config.colors} h-5 w-5`" />
        </div>
        <div class="min-w-0 flex-1">
            <p v-if="title || defaultTitles[props.type ?? 'info']" :class="config.titleColor">
                {{ title || defaultTitles[props.type ?? 'info'] }}
            </p>
            <p :class="`${config.textColor} mt-0.5 text-sm leading-relaxed`">
                <slot>{{ message }}</slot>
            </p>
        </div>
        <button
            v-if="showCloseButton"
            type="button"
            :class="`${config.colors} ml-2 flex-shrink-0 transition-opacity hover:opacity-70`"
            @click="emit('close')"
            aria-label="Cerrar alerta"
        >
            <X class="h-5 w-5" />
        </button>
    </div>
</template>

<style scoped>
@keyframes slideInFromTop {
    from {
        opacity: 0;
        transform: translateY(-0.5rem);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: slideInFromTop 0.2s ease-out;
}
</style>
