<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Image } from 'lucide-vue-next';
import colombiaData from '@/data/colombia.json';

interface CompanyData {
    name: string;
    nit: string;
    phone: string;
    email: string;
    accounting_email: string;
    website: string;
    address: string;
    municipality: string;
    department: string;
    description: string;
    logo_url?: string | null;
}

const props = defineProps<{ company?: Partial<CompanyData> }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Datos básicos', href: '/configuracion/datos-basicos' },
];

const navSections = [
    {
        label: 'Perfil',
        description: 'Datos básicos de la empresa',
        href: '/configuracion/datos-basicos',
        active: true,
    },
    {
        label: 'Accesos y Permisos',
        description: 'Usuarios, roles y accesos',
        href: null,
    },
    {
        label: 'Personalización',
        description: 'Imagen corporativa',
        href: null,
    },
];

const navSectionBaseClasses =
    'flex w-full items-start justify-between gap-2 rounded-xl border px-3 py-3 text-left text-sm transition-colors min-h-[58px] shadow-sm';
const navSectionActiveClasses = 'border-blue-100 bg-blue-50 text-blue-600';
const navSectionDefaultClasses =
    'bg-white border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800';
const navSectionDisabledClasses = 'bg-white border-slate-200 text-slate-400 cursor-not-allowed';

const defaultLogo =
    'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 120 120%22%3E%3Crect width=%22120%22 height=%22120%22 rx=%2216%22 ry=%2216%22 fill=%22%23f0f4f8%22/%3E%3Cpath d=%22M30 40h60v40H30z%22 fill=%22%23ffffff%22 stroke=%22%23d0d7e7%22 stroke-width=%222%22/%3E%3Cpath d=%22M38 48h44M38 56h44M38 64h24%22 stroke=%22%2393a3b8%22 stroke-width=%224%22 stroke-linecap=%22round%22/%3E%3Ccircle cx=%2286%22 cy=%2254%22 r=%226%22 fill=%22%23aac9ff%22/%3E%3Cpath d=%22M56 34h14a4 4 0 0 1 4 4v6h-22v-6a4 4 0 0 1 4-4z%22 fill=%22%23a5b6c8%22/%3E%3C/svg%3E';

const defaultCompanyInfo: CompanyData = {
    name: '',
    nit: '',
    phone: '',
    email: '',
    accounting_email: '',
    website: '',
    address: '',
    municipality: '',
    department: '',
    description: '',
    logo_url: null,
};

const companyInfo = reactive<CompanyData>({ ...defaultCompanyInfo });
if (props.company) {
    Object.assign(companyInfo, props.company);
}

const savedCompanyState = ref<CompanyData>({ ...companyInfo });

const fieldKeys = [
    'name',
    'nit',
    'phone',
    'email',
    'accounting_email',
    'website',
    'address',
    'municipality',
    'department',
    'description',
] as const;

const requiredFieldKeys = [
    'name',
    'nit',
    'email',
    'phone',
    'address',
    'municipality',
    'department',
] as const;

const missingRequiredFields = ref<string[]>([]);

const requiredFieldLabels: Record<string, string> = {
    name: 'Nombre',
    nit: 'NIT',
    email: 'Correo corporativo',
    phone: 'Teléfono principal',
    address: 'Dirección',
    municipality: 'Municipio',
    department: 'Departamento',
};

const isFormReadyForAutoSave = computed(() => {
    const info = companyInfo as Record<string, string | null>;
    return requiredFieldKeys.every((field) => Boolean(info[field]?.toString().trim()));
});

const AUTO_SAVE_DELAY = 900;
const autoSaveTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const isApplyingServerData = ref(false);
const isInitialLoad = ref(true);
// Controla si el auto-save está activo. Por defecto desactivado para guardar sólo al pulsar "Guardar".
const enableAutoSave = ref(false);

const isFieldMissing = (field: string) => missingRequiredFields.value.includes(field);

function validateRequiredFields(): boolean {
    const missing: string[] = [];
    const plainInfo = companyInfo as Record<string, string>;
    requiredFieldKeys.forEach((field) => {
        const value = plainInfo[field];
        if (!value || !value.trim()) {
            missing.push(field);
        }
    });
    missingRequiredFields.value = missing;
    return missing.length === 0;
}

type LocationOption = {
    id: string;
    name: string;
    searchText: string;
};

const allLocationOptions: LocationOption[] = (colombiaData as Record<string, any>[]).flatMap(
    (department) =>
        (department.ciudades || []).map((municipality: string) => ({
            id: `${municipality}||${department.departamento}`,
            name: `${municipality}, ${department.departamento}`,
            searchText: `${municipality.toLowerCase()} ${department.departamento.toLowerCase()}`,
        })),
);

const locationOptions = ref<LocationOption[]>(allLocationOptions.slice(0, 60));
const isLoadingLocationOptions = ref(false);

const locationValue = computed({
    get() {
        if (!companyInfo.municipality || !companyInfo.department) {
            return '';
        }
        return `${companyInfo.municipality}||${companyInfo.department}`;
    },
    set(value: string) {
        if (!value) {
            companyInfo.municipality = '';
            companyInfo.department = '';
            return;
        }
        const [municipality, department] = value.split('||');
        companyInfo.municipality = municipality || '';
        companyInfo.department = department || '';
    },
});

const updateLocationOptions = (searchQuery: string = '') => {
    const query = searchQuery.toLowerCase().trim();
    if (!query) {
        locationOptions.value = allLocationOptions.slice(0, 60);
        return;
    }
    if (isLoadingLocationOptions.value) {
        return;
    }
    isLoadingLocationOptions.value = true;
    setTimeout(() => {
        locationOptions.value = allLocationOptions
            .filter((option) => option.searchText.includes(query))
            .slice(0, 100);
        isLoadingLocationOptions.value = false;
    }, 0);
};

const logoPreviewUrl = ref<string | null>(null);
const iconInputRef = ref<HTMLInputElement | null>(null);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const isSaving = ref(false);
const logoFile = ref<File | null>(null);

const companyLogo = computed(() => logoPreviewUrl.value ?? companyInfo.logo_url ?? defaultLogo);
const hasCustomLogo = computed(() => Boolean(logoPreviewUrl.value));

function triggerLogoUpload(): void {
    iconInputRef.value?.click();
}

function processSelectedLogo(file: File): void {
    logoFile.value = file;
    const reader = new FileReader();
    reader.onload = (event) => {
        logoPreviewUrl.value = event.target?.result as string;
    };
    reader.readAsDataURL(file);
}

function handleLogoChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) {
        return;
    }
    processSelectedLogo(file);
    target.value = '';
}

function handleLogoDrop(event: DragEvent): void {
    event.preventDefault();
    const file = event.dataTransfer?.files?.[0];
    if (!file) {
        return;
    }
    processSelectedLogo(file);
}

function resetLogoPreview(): void {
    logoPreviewUrl.value = null;
    logoFile.value = null;
}

function resetForm(): void {
    isApplyingServerData.value = true;
    Object.assign(companyInfo, savedCompanyState.value);
    isApplyingServerData.value = false;
    missingRequiredFields.value = [];
    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
        autoSaveTimer.value = null;
    }
    successMessage.value = '';
    setError('', 'error');
    resetLogoPreview();
}

const setError = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    errorMessage.value = message;
    errorType.value = type;
};

type SyncOptions = {
    showSuccessMessage?: boolean;
};

async function syncCompanyInfo(options: SyncOptions = {}): Promise<void> {
    const { showSuccessMessage = false } = options;

    if (isSaving.value) {
        return;
    }

    isSaving.value = true;

    try {
        const payload = new FormData();
        const plainInfo = companyInfo as Record<string, string | null>;
        fieldKeys.forEach((field) => {
            payload.append(field, plainInfo[field] ?? '');
        });

        if (logoFile.value) {
            payload.append('logo', logoFile.value);
        }

        payload.append('_method', 'PATCH');
        const response = await axios.post('/configuracion/datos-basicos', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        isApplyingServerData.value = true;
        const company = response.data?.company as Partial<CompanyData> | undefined;
        if (company) {
            Object.assign(companyInfo, company);
            savedCompanyState.value = { ...companyInfo };
            // Clear validation state after a successful save
            missingRequiredFields.value = [];
            errorMessage.value = '';
        }
        isApplyingServerData.value = false;

        resetLogoPreview();

        if (showSuccessMessage) {
            successMessage.value = response.data?.message ?? 'Los datos básicos se actualizaron correctamente.';
            setError('', 'error');
            // Recargar datos compartidos por Inertia (por ejemplo el layout que muestra el logo)
            try {
                router.reload();
            } catch {
                // Fallback a recarga completa si router.reload no está disponible
                window.location.reload();
            }
        } else {
            errorMessage.value = '';
        }
    } catch (error) {
        console.error('Error guardando datos básicos:', error);
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const validationErrors = error.response?.data?.errors as Record<string, string[]> | undefined;
            const errorFields = validationErrors ? Object.keys(validationErrors) : [];
            const labelList = errorFields
                .map((field) => requiredFieldLabels[field] ?? field)
                .join(', ');
            const friendlyMessage = labelList
                ? `Corrige los campos obligatorios: ${labelList}.`
                : 'Revisa los campos obligatorios marcados.';
            setError(friendlyMessage, 'warning');
        } else {
            setError('No se pudo guardar la información. Intenta nuevamente.', 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

function scheduleAutoSave(): void {
    // No ejecutar si el autosave está desactivado (guardado sólo al pulsar botón)
    if (!enableAutoSave.value) {
        return;
    }

    if (!isFormReadyForAutoSave.value || isSaving.value) {
        return;
    }

    missingRequiredFields.value = [];

    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
    }

    autoSaveTimer.value = setTimeout(() => {
        autoSaveTimer.value = null;
        syncCompanyInfo();
    }, AUTO_SAVE_DELAY);
}

async function saveBasicData(): Promise<void> {
    successMessage.value = '';
    setError('', 'error');
    missingRequiredFields.value = [];

    if (!validateRequiredFields()) {
        const labels = missingRequiredFields.value
            .map((field) => requiredFieldLabels[field] ?? field)
            .join(', ');
        setError(`Completa los campos obligatorios: ${labels}.`, 'warning');
        return;
    }

    await syncCompanyInfo({ showSuccessMessage: true });
}

watch(
    () => fieldKeys.map((field) => companyInfo[field]),
    () => {
        // Solo programar auto-save si está habilitado y no estamos aplicando datos del servidor
        if (!enableAutoSave.value || isApplyingServerData.value || isInitialLoad.value) {
            return;
        }
        scheduleAutoSave();
    },
);

onMounted(() => {
    isInitialLoad.value = false;
});

onBeforeUnmount(() => {
    resetLogoPreview();
    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
        autoSaveTimer.value = null;
    }
});
</script>

<template>
    <Head title="Configuración · Datos básicos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            success-title="¡Listo!"
            error-title="Error"
            :error-type="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex flex-1 flex-col gap-6 rounded-xl p-3">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-slate-900">Datos básicos</h1>
                <p class="max-w-2xl text-sm text-slate-500">
                    Actualiza la información fiscal, de contacto e identidad visual que usa toda la
                    plataforma.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)] items-start">
                <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="space-y-3">
                        <div class="relative h-28 w-full overflow-hidden rounded-2xl bg-slate-100">
                            <img
                                :src="companyLogo"
                                alt="Logotipo de la empresa"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="relative space-y-1">
                            <p class="text-xs font-semibold uppercase  text-slate-400">Empresa</p>
                            <h2 class="text-s font-semibold text-slate-900">{{ companyInfo.name || 'Sin nombre' }}</h2>
                            <p class="text-xs text-slate-500">NIT: {{ companyInfo.nit || '-' }}</p>
                            <p class="text-xs text-slate-500">
                                Ubicación: {{ companyInfo.address || '-' }}. {{ companyInfo.municipality || '-' }}, {{ companyInfo.department || '-' }}.
                            </p>
                            <p class="text-xs text-slate-500">Teléfono: {{ companyInfo.phone || '-' }}</p>

                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div class="text-xs font-semibold uppercase  text-slate-400">
                            Secciones
                        </div>
                        <div class="space-y-2">
                            <template v-for="section in navSections" :key="section.label">
                                <Link
                                    v-if="section.href"
                                    :href="section.href"
                                    :class="[navSectionBaseClasses, section.active ? navSectionActiveClasses : navSectionDefaultClasses]"
                                    :aria-current="section.active ? 'page' : undefined"
                                >
                                    <div>
                                        <p class="font-semibold">{{ section.label }}</p>
                                        <p class="text-xs text-slate-400">{{ section.description }}</p>
                                    </div>
                                    <span
                                        v-if="section.active"
                                        class="text-[10px] font-semibold uppercase  text-blue-600"
                                    >
                                        Activo
                                    </span>
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    disabled
                                    :class="[navSectionBaseClasses, navSectionDisabledClasses]"
                                    title="Sección en desarrollo"
                                >
                                    <div>
                                        <p class="font-semibold">{{ section.label }}</p>
                                        <p class="text-xs text-slate-400">{{ section.description }}</p>
                                    </div>
                                    <span class="text-[10px] font-semibold uppercase ">Próximo</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </aside>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <form class="space-y-6" @submit.prevent="saveBasicData">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="legal-name" class="text-sm font-medium text-slate-700">
                                    Nombre <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    id="legal-name"
                                    v-model="companyInfo.name"
                                    :class="isFieldMissing('name') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                            </div>
                            <div class="space-y-2">
                                <label for="nit" class="text-sm font-medium text-slate-700">
                                    NIT <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    id="nit"
                                    v-model="companyInfo.nit"
                                    :class="isFieldMissing('nit') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="email" class="text-sm font-medium text-slate-700">
                                    Correo corporativo <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    id="email"
                                    v-model="companyInfo.email"
                                    :class="isFieldMissing('email') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                            </div>
                            <div class="space-y-2">
                                <label for="accounting-email" class="text-sm font-medium text-slate-700">Correo contable</label>
                                <Input id="accounting-email" v-model="companyInfo.accounting_email" />
                            </div>
                            <div class="space-y-2">
                                <label for="phone" class="text-sm font-medium text-slate-700">
                                    Teléfono principal <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    id="phone"
                                    v-model="companyInfo.phone"
                                    :class="isFieldMissing('phone') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                            </div>
                            <div class="space-y-2">
                                <label for="website" class="text-sm font-medium text-slate-700">Sitio web</label>
                                <Input id="website" v-model="companyInfo.website" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 flex flex-col h-full justify-end">
                                <label for="address" class="text-sm font-medium text-slate-700">
                                    Dirección <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    id="address"
                                    v-model="companyInfo.address"
                                    :class="isFieldMissing('address') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                            </div>
                            <div class="space-y-2 flex flex-col h-full justify-end">
                                <SelectField
                                    v-model="locationValue"
                                    label="Municipio y departamento"
                                    required
                                    :options="locationOptions"
                                    placeholder="Selecciona un municipio o departamento"
                                    :searchable="true"
                                    :searchThreshold="0"
                                    @search="updateLocationOptions"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="description" class="text-sm font-medium text-slate-700">Descripción</label>
                            <Textarea id="description" rows="4" v-model="companyInfo.description" />
                        </div>

                        <div class="mt-6 rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-sm font-semibold text-slate-700">Logotipo corporativo</p>
                            <p class="text-xs text-slate-500 mb-3">
                                Sube PNG, SVG o JPG de máximo 2 MB. Haz clic o arrastra una foto desde tu escritorio.
                            </p>
                            <div class="flex flex-col gap-3">
                                <div
                                    class="relative w-full cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-gray-200 bg-slate-50 transition hover:border-blue-400"
                                    style="min-height: 9rem;"
                                    @click="triggerLogoUpload"
                                    @drop.prevent="handleLogoDrop"
                                    @dragover.prevent
                                >
                                    <template v-if="logoPreviewUrl">
                                        <img
                                            :src="companyLogo"
                                            alt="Logotipo de la empresa"
                                            class="h-full w-full object-cover"
                                        />
                                        <div class="absolute inset-x-0 bottom-0 bg-black/60 py-1 text-[10px] uppercase text-white">
                                            {{ hasCustomLogo ? 'Listo para subir' : 'Sin cambios' }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex h-full flex-col items-center justify-center gap-1 text-xs text-slate-400">
                                            <Image class="h-20 w-6" />
                                            <span class="text-xs text-center leading-tight">
                                                Haz clic o arrastra una imagen.
                                            </span>
                                        </div>
                                    </template>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="self-start"
                                    :disabled="!hasCustomLogo"
                                    @click="resetLogoPreview"
                                >
                                    Restablecer
                                </Button>
                            </div>
                            <input
                                ref="iconInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleLogoChange"
                            />
                        </div>

                        <div class="flex flex-wrap gap-3 border-t pt-4">
                            <Button type="submit" variant="default" :disabled="isSaving">
                                {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                            </Button>
                            <Button type="button" variant="outline" :disabled="isSaving" @click="resetForm">
                                Restablecer valores
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
