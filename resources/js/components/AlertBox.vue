<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
    type?: 'success' | 'error' | 'info' | 'warning';
    title?: string;
    message: string;
    autoCloseMs?: number;
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
    success: 'Éxito',
    error: 'Error',
    info: 'Información',
    warning: 'Advertencia',
};

const defaultAutoCloseMs: Record<string, number> = {
    success: 4000,
    error: 6000,
    warning: 5000,
    info: 4000,
};

const sanitizedMessage = computed(() => {
    const msg = props.message ?? '';
    const isTechnical =
        msg.length > 300 ||
        msg.includes('Exception') ||
        msg.includes('Stack trace') ||
        msg.includes('at line') ||
        msg.includes('/var/www') ||
        msg.includes('\\App\\') ||
        msg.includes('<br>') ||
        msg.includes('SQLSTATE') ||
        /^\s*</.test(msg);

    if (isTechnical) {
        const fallbacks: Record<string, string> = {
            success: 'La operación se realizó correctamente.',
            error: 'Ocurrió un error al procesar la solicitud. Por favor intenta de nuevo.',
            warning: 'Verifica la información antes de continuar.',
            info: 'Operación procesada.',
        };
        return fallbacks[props.type ?? 'info'] ?? 'Ocurrió un error inesperado.';
    }
    return msg;
});

let timeoutId: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.message,
    (val) => {
        if (!val) return;
        if (timeoutId) clearTimeout(timeoutId);

        const ms = props.autoCloseMs ?? defaultAutoCloseMs[props.type ?? 'info'] ?? 4000;
        timeoutId = setTimeout(() => emit('close'), ms);
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
        data-alert-stack-root="true"
        @pointerdown.stop
        @mousedown.stop
        @touchstart.stop
        @click.stop
    >
        <div class="flex flex-shrink-0 items-start pt-0.5">
            <component :is="IconComponent" :class="`${config.colors} h-5 w-5`" />
        </div>
        <div class="min-w-0 flex-1">
            <p v-if="title || defaultTitles[props.type ?? 'info']" :class="config.titleColor">
                {{ title || defaultTitles[props.type ?? 'info'] }}
            </p>
            <p :class="`${config.textColor} mt-0.5 text-sm leading-relaxed`">
                <slot>{{ sanitizedMessage }}</slot>
            </p>
        </div>
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
