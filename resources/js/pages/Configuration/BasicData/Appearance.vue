<script setup lang="ts">
import { Button } from '@/components/ui/button';
import SelectField from '@/components/SelectField.vue';
import { ref } from 'vue';

type ThemeOption = 'light' | 'dark' | 'system';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perfil',
        href: '/panel-administrativo/perfil',
    },
];

const themePreferenceOptions = [
    { value: 'light', label: 'Claro', description: 'Colores vivos y fondos claros' },
    { value: 'dark', label: 'Oscuro', description: 'Contrastes altos para ambientes con poca luz' },
    { value: 'system', label: 'Sistema', description: 'Sincroniza con el tema del sistema operativo' },
] as const;

const themeLabels: Record<ThemeOption, string> = {
    light: 'Claro',
    dark: 'Oscuro',
    system: 'Sistema',
};

const selectedTheme = ref<ThemeOption>('system');
const personalizationMessage = ref('');
const personalizationSaving = ref(false);

const emit = defineEmits<{
    (e: 'success', message: string): void;
    (e: 'error', message: string): void;
}>();

function selectThemeOption(theme: ThemeOption) {
    selectedTheme.value = theme;
    personalizationMessage.value = '';
}

function resetThemePreference() {
    selectThemeOption('system');
    personalizationMessage.value = '';
}

function savePersonalizationSettings() {
    personalizationSaving.value = true;
    const message = `Se guardó el tema ${themeLabels[selectedTheme.value]}.`;
    personalizationMessage.value = message;
    emit('success', message);
    personalizationSaving.value = false;
}
</script>

<template>
    <Head title="Perfil · Configuración" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold text-slate-900">Personalización</h2>
                <p class="text-sm text-slate-500">Elige el tema que mejor se adapta a tu entorno de trabajo.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    v-for="option in themePreferenceOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-xl border px-3 py-4 text-left transition"
                    :class="selectedTheme === option.value
                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                        : 'border-gray-200 bg-white text-slate-700 hover:border-slate-300'"
                    @click="selectThemeOption(option.value)"
                >
                    <p class="text-sm font-semibold">{{ option.label }}</p>
                    <p class="text-xs text-slate-400">{{ option.description }}</p>
                </button>
            </div>
            <div class="flex flex-wrap gap-3 border-t pt-4">
                <Button type="button" variant="default" :disabled="personalizationSaving" @click="savePersonalizationSettings">
                    {{ personalizationSaving ? 'Guardando...' : 'Guardar tema' }}
                </Button>
                <Button type="button" variant="outline" :disabled="personalizationSaving" @click="resetThemePreference">
                    Restablecer al sistema
                </Button>
            </div>
            <p v-if="personalizationMessage" class="text-sm font-medium text-blue-600">{{ personalizationMessage }}</p>
        </div>
    </AppLayout>
</template>
