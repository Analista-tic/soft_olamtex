<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import SelectField from '@/components/SelectField.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import colombiaData from '@/data/colombia.json';
import type { BreadcrumbItem, SharedData, User } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Camera, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppearanceSection from './Appearance.vue';
import ProfileSection from './Profile.vue';
import AvatarCropDialog from './Profile/AvatarCropDialog.vue';

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

const props = defineProps<{ company?: Partial<CompanyData>; activeSection?: SectionKey }>();
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
const auth = useAuthStore();
const canEditCompany = computed(() => auth.hasRole('admin'));
const rawAvatarUrl = computed(() => (user.value?.avatar_url ?? '').trim());
const currentAvatarUrl = computed(() => {
    if (!rawAvatarUrl.value) {
        return '';
    }

    const version = user.value?.updated_at ? encodeURIComponent(String(user.value.updated_at)) : '';
    const separator = rawAvatarUrl.value.includes('?') ? '&' : '?';
    return version ? `${rawAvatarUrl.value}${separator}v=${version}` : rawAvatarUrl.value;
});
const hasAvatar = computed(() => Boolean(rawAvatarUrl.value));
const ALERT_AUTO_CLOSE_MS = 3000;

// Avatar state
const avatarFileInputRef = ref<HTMLInputElement | null>(null);
const isAvatarMenuOpen = ref(false);
const isCropDialogOpen = ref(false);
const pendingAvatarFile = ref<File | null>(null);

// Computed para obtener las iniciales del usuario
const userInitials = computed(() => {
    const name = user.value?.name || '';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
});

function syncAuthFromServer(): Promise<void> {
    return new Promise((resolve) => {
        router.reload({
            only: ['auth'],
            onFinish: () => resolve(),
        });
    });
}

// Funciones para manejar el avatar
function triggerAvatarFileSelect() {
    avatarFileInputRef.value?.click();
}

function handleChangeAvatarAction() {
    isAvatarMenuOpen.value = false;
    triggerAvatarFileSelect();
}

function handleAvatarFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    // Validar tipo
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showErrorAlert('Solo se permiten imágenes JPG, PNG o WebP.');
        return;
    }

    // Validar tamaño (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showErrorAlert('La imagen no debe superar los 5MB.');
        return;
    }

    // Abrir dialog de crop con el archivo
    pendingAvatarFile.value = file;
    isCropDialogOpen.value = true;

    // Limpiar input para permitir seleccionar el mismo archivo de nuevo
    target.value = '';
}

async function deleteAvatar() {
    isAvatarMenuOpen.value = false;

    try {
        await axios.delete(route('profile.avatar.destroy'));
        pendingAvatarFile.value = null;
        await syncAuthFromServer();
        showSuccessAlert('Avatar eliminado correctamente.');
    } catch {
        showErrorAlert('No se pudo eliminar el avatar.');
    }
}

async function handleAvatarSuccess(message: string) {
    pendingAvatarFile.value = null; // Limpiar archivo pendiente
    await syncAuthFromServer();
    showSuccessAlert(message);
}

function handleAvatarError(message: string) {
    showErrorAlert(message);
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datos Básicos',
        href: '/configuracion/empresa',
    },
];

type SectionKey = 'empresa' | 'perfil' | 'personalizacion';

const sectionRoutes: Record<SectionKey, string> = {
    empresa: route('configuracion.empresa'),
    perfil: route('configuracion.perfil'),
    personalizacion: route('configuracion.apariencia'),
};

type NavSection = {
    key: SectionKey;
    label: string;
    description: string;
    href?: string;
};

const navSections: NavSection[] = [
    {
        key: 'empresa',
        label: 'Empresa',
        description: 'Configuración corporativa',
    },
    {
        key: 'perfil',
        label: 'Perfil',
        description: 'Información de tu cuenta',
        href: '/configuracion/perfil',
    },
    {
        key: 'personalizacion',
        label: 'Personalización',
        description: 'Tema y apariencia de la plataforma',
        href: '/configuracion/personalizacion',
    },
];

const selectedSection = ref<SectionKey>(props.activeSection ?? 'empresa');

const goToSection = (key: SectionKey) => {
    if (selectedSection.value === key) {
        return;
    }
    const destination = sectionRoutes[key] ?? sectionRoutes.empresa;
    selectedSection.value = key;
    router.visit(destination, { preserveScroll: true, replace: true });
};

const navSectionBaseClasses =
    'flex w-full items-start justify-between gap-2 rounded-xl border px-3 py-3 text-left text-sm transition-colors min-h-[58px] shadow-sm bg-card border-border dark:border-border';
const navSectionActiveClasses = 'border-primary bg-primary/10 text-primary';
const navSectionDefaultClasses =
    'text-muted-foreground/80 hover:border-slate-300 hover:bg-slate-50 hover:text-card-foreground dark:text-muted-foreground/60 dark:hover:border-slate-600 dark:hover:bg-slate-900 dark:hover:text-white';

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

const fieldKeys = ['name', 'nit', 'phone', 'email', 'accounting_email', 'website', 'address', 'municipality', 'department', 'description'] as const;

const requiredFieldKeys = ['name', 'nit', 'email', 'phone', 'address', 'municipality', 'department'] as const;

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

const allLocationOptions: LocationOption[] = (colombiaData as Record<string, any>[]).flatMap((department) =>
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
        locationOptions.value = allLocationOptions.filter((option) => option.searchText.includes(query)).slice(0, 100);
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
    if (!canEditCompany.value) {
        return;
    }
    iconInputRef.value?.click();
}

function processSelectedLogo(file: File): void {
    if (!canEditCompany.value) {
        return;
    }
    logoFile.value = file;
    const reader = new FileReader();
    reader.onload = (event) => {
        logoPreviewUrl.value = event.target?.result as string;
    };
    reader.readAsDataURL(file);
}

function handleLogoChange(event: Event): void {
    if (!canEditCompany.value) {
        return;
    }
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) {
        return;
    }
    processSelectedLogo(file);
    target.value = '';
}

function handleLogoDrop(event: DragEvent): void {
    if (!canEditCompany.value) {
        return;
    }
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

const clearAlerts = () => {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
};

const showSuccessAlert = (message: string) => {
    successMessage.value = message;
    errorMessage.value = '';
};

const showErrorAlert = (message: string, type: 'error' | 'warning' | 'info' = 'error') => {
    errorType.value = type;
    errorMessage.value = message;
    if (message) {
        successMessage.value = '';
    }
};

const handleProfileSuccess = (message: string) => {
    showSuccessAlert(message);
};

const handleProfileError = (message: string) => {
    showErrorAlert(message);
};

const handleAppearanceSuccess = (message: string) => {
    showSuccessAlert(message);
};

const handleAppearanceError = (message: string) => {
    showErrorAlert(message);
};

const setError = (message: string, type: 'error' | 'warning' | 'info' = 'error') => {
    showErrorAlert(message, type);
};

const alertTitleForType = (type: 'success' | 'error' | 'info' | 'warning') => {
    switch (type) {
        case 'success':
            return '¡Listo!';
        case 'warning':
            return 'Advertencia';
        case 'info':
            return 'Información';
        default:
            return 'Error';
    }
};

const activeAlert = computed(() => {
    if (successMessage.value) {
        return {
            message: successMessage.value,
            type: 'success' as const,
            title: alertTitleForType('success'),
        };
    }
    if (errorMessage.value) {
        return {
            message: errorMessage.value,
            type: errorType.value,
            title: alertTitleForType(errorType.value),
        };
    }
    return null;
});

function resetForm(): void {
    isApplyingServerData.value = true;
    Object.assign(companyInfo, savedCompanyState.value);
    isApplyingServerData.value = false;
    missingRequiredFields.value = [];
    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
        autoSaveTimer.value = null;
    }
    clearAlerts();
    setError('', 'error');
    resetLogoPreview();
}

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
        const response = await axios.post('/configuracion/empresa', payload, {
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
            showSuccessAlert(response.data?.message ?? 'Los datos básicos se actualizaron correctamente.');
            try {
                router.reload();
            } catch {
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
            const labelList = errorFields.map((field) => requiredFieldLabels[field] ?? field).join(', ');
            const friendlyMessage = labelList ? `Corrige los campos obligatorios: ${labelList}.` : 'Revisa los campos obligatorios marcados.';
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
    clearAlerts();
    missingRequiredFields.value = [];

    if (!canEditCompany.value) {
        setError('Solo administradores pueden modificar información de empresa.', 'warning');
        return;
    }

    if (!validateRequiredFields()) {
        const labels = missingRequiredFields.value.map((field) => requiredFieldLabels[field] ?? field).join(', ');
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

watch(
    () => props.activeSection,
    (value) => {
        if (value) {
            selectedSection.value = value;
        }
    },
);

onMounted(async () => {
    try {
        await auth.fetchUser();
    } catch {
        // no-op: la vista mantiene modo solo lectura si no se cargan roles
    }
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
    <Head title="Datos Básicos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <transition name="fade">
            <div v-if="activeAlert" class="pointer-events-none fixed left-1/2 top-4 z-[9999] flex w-full max-w-xl -translate-x-1/2 px-3">
                <AlertBox
                    class="pointer-events-auto w-full"
                    :type="activeAlert.type"
                    :title="activeAlert.title"
                    :message="activeAlert.message"
                    :auto-close-ms="ALERT_AUTO_CLOSE_MS"
                    @close="clearAlerts"
                />
            </div>
        </transition>
        <div class="flex flex-1 flex-col gap-6 rounded-xl p-3">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-card-foreground">Datos Básicos</h1>
                <p class="max-w-2xl text-sm text-muted-foreground/70">
                    Centraliza la información corporativa que utiliza el sistema para identificar la empresa, comunicar con contactos y contextualizar
                    los reportes operativos.
                </p>
            </div>

            <div class="grid items-start gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <!-- Preview del usuario cuando está en sección perfil -->
                    <div v-if="selectedSection !== 'empresa'" class="space-y-3">
                        <div class="flex flex-col items-center py-4">
                            <!-- Avatar con botón fijo de edición - NO se mueve -->
                            <div class="relative inline-block h-28 w-28">
                                <Avatar class="h-28 w-28 shadow-lg ring-4 ring-white">
                                    <AvatarImage
                                        v-if="currentAvatarUrl"
                                        :key="user.avatar_url ?? 'no-avatar'"
                                        :src="currentAvatarUrl"
                                        :alt="user.name"
                                    />
                                    <AvatarFallback class="bg-gradient-to-br from-blue-500 to-indigo-600 text-2xl font-semibold text-white">
                                        {{ userInitials }}
                                    </AvatarFallback>
                                </Avatar>
                                <!-- Botón de cámara - position absolute, tamaño fijo -->
                                <DropdownMenu v-model:open="isAvatarMenuOpen" :modal="false">
                                    <DropdownMenuTrigger :as-child="true">
                                        <button
                                            type="button"
                                            class="absolute bottom-0 right-0 z-10 box-border inline-flex h-9 w-9 items-center justify-center rounded-full border border-transparent bg-primary text-white shadow-md transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 data-[state=open]:bg-primary"
                                            aria-label="Acciones de foto de perfil"
                                        >
                                            <Camera class="h-4 w-4" />
                                        </button>
                                    </DropdownMenuTrigger>

                                    <DropdownMenuContent align="end" side="bottom" :side-offset="10" class="w-44 rounded-xl">
                                        <DropdownMenuItem class="cursor-pointer" @select.prevent="handleChangeAvatarAction">
                                            <Camera class="mr-2 h-4 w-4" />
                                            <span>Cambiar foto</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="hasAvatar"
                                            class="cursor-pointer text-destructive focus:text-destructive"
                                            @select.prevent="deleteAvatar"
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            <span>Eliminar foto</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <!-- Input file oculto -->
                            <input
                                ref="avatarFileInputRef"
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp"
                                class="hidden"
                                @change="handleAvatarFileChange"
                            />
                        </div>
                        <div class="space-y-1 text-center">
                            <p class="text-xs font-semibold uppercase text-muted-foreground/70">Usuario</p>
                            <h2 class="text-lg font-semibold text-card-foreground">{{ user.name || 'Sin nombre' }}</h2>
                            <p class="text-sm text-muted-foreground/70">{{ user.email || '-' }}</p>
                        </div>
                    </div>

                    <!-- Preview de empresa en otras secciones -->
                    <div v-else class="space-y-3">
                        <div class="relative h-28 w-full overflow-hidden rounded-2xl bg-slate-100">
                            <img :src="companyLogo" alt="Logotipo de la empresa" class="h-full w-full object-cover" />
                        </div>
                        <div class="relative space-y-1">
                            <p class="text-xs font-semibold uppercase text-muted-foreground/70">Empresa</p>
                            <h2 class="text-s font-semibold text-card-foreground">{{ companyInfo.name || 'Sin nombre' }}</h2>
                            <p class="text-xs text-muted-foreground/70">NIT: {{ companyInfo.nit || '-' }}</p>
                            <p class="text-xs text-muted-foreground/70">
                                Ubicación: {{ companyInfo.address || '-' }}. {{ companyInfo.municipality || '-' }},
                                {{ companyInfo.department || '-' }}.
                            </p>
                            <p class="text-xs text-muted-foreground/70">Correo: {{ companyInfo.email || '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div class="text-xs font-semibold uppercase text-muted-foreground/70">Secciones</div>
                        <div class="space-y-2">
                            <template v-for="section in navSections" :key="section.key">
                                <button
                                    type="button"
                                    class="group w-full"
                                    :class="[
                                        navSectionBaseClasses,
                                        selectedSection === section.key ? navSectionActiveClasses : navSectionDefaultClasses,
                                    ]"
                                    @click="goToSection(section.key)"
                                    :aria-current="selectedSection === section.key ? 'page' : undefined"
                                >
                                    <div>
                                        <p class="font-semibold text-card-foreground">{{ section.label }}</p>
                                        <p class="text-xs text-muted-foreground/70">{{ section.description }}</p>
                                    </div>
                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-wide"
                                        :class="selectedSection === section.key ? 'text-primary' : 'text-muted-foreground/60'"
                                    >
                                        {{ selectedSection === section.key ? 'Activo' : 'Disponible' }}
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </aside>

                <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <div v-if="selectedSection === 'empresa'" class="space-y-4">
                        <div v-if="!canEditCompany" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Solo administradores pueden modificar informacion de empresa.
                        </div>
                        <form class="space-y-6" @submit.prevent="saveBasicData">
                            <fieldset class="space-y-6" :disabled="!canEditCompany">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label for="legal-name" class="text-sm font-medium text-card-foreground">
                                            Nombre <span class="text-red-500">*</span>
                                        </label>
                                        <Input
                                            id="legal-name"
                                            v-model="companyInfo.name"
                                            :class="isFieldMissing('name') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label for="nit" class="text-sm font-medium text-card-foreground">
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
                                        <label for="email" class="text-sm font-medium text-card-foreground">
                                            Correo corporativo <span class="text-red-500">*</span>
                                        </label>
                                        <Input
                                            id="email"
                                            v-model="companyInfo.email"
                                            :class="isFieldMissing('email') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label for="accounting-email" class="text-sm font-medium text-card-foreground">Correo contable</label>
                                        <Input id="accounting-email" v-model="companyInfo.accounting_email" />
                                    </div>
                                    <div class="space-y-2">
                                        <label for="phone" class="text-sm font-medium text-card-foreground">
                                            Teléfono principal <span class="text-red-500">*</span>
                                        </label>
                                        <Input
                                            id="phone"
                                            v-model="companyInfo.phone"
                                            :class="isFieldMissing('phone') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label for="website" class="text-sm font-medium text-card-foreground">Sitio web</label>
                                        <Input id="website" v-model="companyInfo.website" />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="flex h-full flex-col justify-end space-y-2">
                                        <label for="address" class="text-sm font-medium text-card-foreground">
                                            Dirección <span class="text-red-500">*</span>
                                        </label>
                                        <Input
                                            id="address"
                                            v-model="companyInfo.address"
                                            :class="isFieldMissing('address') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                                        />
                                    </div>
                                    <div class="flex h-full flex-col justify-end space-y-2">
                                        <SelectField
                                            v-model="locationValue"
                                            label="Municipio y departamento"
                                            required
                                            :disabled="!canEditCompany"
                                            :options="locationOptions"
                                            placeholder="Selecciona un municipio o departamento"
                                            :searchable="true"
                                            :searchThreshold="0"
                                            @search="updateLocationOptions"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label for="description" class="text-sm font-medium text-card-foreground">Descripción</label>
                                    <Textarea id="description" rows="4" v-model="companyInfo.description" />
                                </div>

                                <div class="mt-6 rounded-2xl border border-dashed border-border bg-card p-4">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-card-foreground">Logotipo corporativo</p>
                                        <HelpTooltip
                                            text="Sube un logotipo que represente a tu marca."
                                            aria-label="Ayuda sobre el logotipo corporativo"
                                        />
                                    </div>
                                    <p class="m-2 text-xs text-muted-foreground/70">
                                        Sube PNG, SVG o JPG de máximo 2 MB. Haz clic o arrastra una foto desde tu escritorio.
                                    </p>
                                    <div class="flex flex-col gap-3">
                                        <div
                                            class="relative w-full overflow-hidden rounded-2xl border-2 border-dashed border-border bg-muted transition dark:border-border dark:bg-slate-900"
                                            :class="canEditCompany ? 'cursor-pointer hover:border-primary' : 'cursor-not-allowed opacity-70'"
                                            style="min-height: 9rem"
                                            @click="canEditCompany && triggerLogoUpload()"
                                            @drop.prevent="canEditCompany && handleLogoDrop($event)"
                                            @dragover.prevent
                                        >
                                            <template v-if="logoPreviewUrl">
                                                <img :src="companyLogo" alt="Logotipo de la empresa" class="h-full w-full object-cover" />
                                                <div class="absolute inset-x-0 bottom-0 bg-black/60 py-1 text-[10px] uppercase text-white">
                                                    {{ hasCustomLogo ? 'Listo para subir' : 'Sin cambios' }}
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="flex h-full flex-col items-center justify-center gap-1 text-xs text-muted-foreground/60">
                                                    <Camera class="h-6 w-6" />
                                                    <span class="text-center text-xs leading-tight"> Haz clic o arrastra una imagen. </span>
                                                </div>
                                            </template>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-1 self-start rounded-full border border-border/80 bg-background px-3 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!hasCustomLogo || !canEditCompany"
                                            @click="resetLogoPreview"
                                        >
                                            Restablecer
                                        </button>
                                    </div>
                                    <input
                                        ref="iconInputRef"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        :disabled="!canEditCompany"
                                        @change="handleLogoChange"
                                    />
                                </div>
                            </fieldset>

                            <div class="flex flex-wrap gap-3 border-t pt-4">
                                <button
                                    type="submit"
                                    :disabled="isSaving || !canEditCompany"
                                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="isSaving || !canEditCompany"
                                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="resetForm"
                                >
                                    Restablecer valores
                                </button>
                            </div>
                        </form>
                    </div>
                    <div v-else-if="selectedSection === 'perfil'">
                        <ProfileSection :user="user" @success="handleProfileSuccess" @error="handleProfileError" />
                    </div>
                    <div v-else>
                        <AppearanceSection @success="handleAppearanceSuccess" @error="handleAppearanceError" />
                    </div>
                </section>
            </div>
        </div>

        <!-- Dialog de Avatar Crop -->
        <AvatarCropDialog
            v-model="isCropDialogOpen"
            :image-file="pendingAvatarFile"
            :has-current-avatar="hasAvatar"
            @success="handleAvatarSuccess"
            @error="handleAvatarError"
            @delete="deleteAvatar"
        />
    </AppLayout>
</template>
