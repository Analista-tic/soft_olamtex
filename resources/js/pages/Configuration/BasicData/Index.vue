<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { Image } from 'lucide-vue-next';

interface CompanyData {
    name: string;
    brand: string;
    document_type: string;
    nit: string;
    phone: string;
    email: string;
    accounting_email: string;
    website: string;
    address: string;
    city: string;
    country: string;
    industry: string;
    currency: string;
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
        description: 'Datos básicos y fiscales',
        href: '/configuracion/datos-basicos',
        active: true,
    },
    {
        label: 'Seguridad',
        description: 'Usuarios, roles y accesos',
        href: null,
    },
    {
        label: 'Personalización',
        description: 'Imagen corporativa',
        href: null,
    },
];

const defaultLogo =
    'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=256&q=80';

const defaultCompanyInfo: CompanyData = {
    name: '',
    brand: '',
    document_type: '',
    nit: '',
    phone: '',
    email: '',
    accounting_email: '',
    website: '',
    address: '',
    city: '',
    country: '',
    industry: '',
    currency: '',
    description: '',
    logo_url: null,
};

const companyInfo = reactive<CompanyData>({ ...defaultCompanyInfo });
if (props.company) {
    Object.assign(companyInfo, props.company);
}

const savedCompanyState = ref<CompanyData>({ ...companyInfo });

const quickFacts = computed(() => [
    { label: 'NIT', value: companyInfo.nit || 'Sin definir' },
    { label: 'Documento', value: companyInfo.document_type || 'Sin definir' },
    {
        label: 'Ubicación',
        value:
            [companyInfo.city, companyInfo.country].filter(Boolean).join(', ') || 'Sin definir',
    },
    { label: 'Industria principal', value: companyInfo.industry || 'Sin definir' },
]);

const fieldKeys = [
    'name',
    'brand',
    'document_type',
    'nit',
    'phone',
    'email',
    'accounting_email',
    'website',
    'address',
    'city',
    'country',
    'industry',
    'currency',
    'description',
] as const;

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
    Object.assign(companyInfo, savedCompanyState.value);
    successMessage.value = '';
    setError('', 'error');
    resetLogoPreview();
}

const setError = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    errorMessage.value = message;
    errorType.value = type;
};

async function saveBasicData(): Promise<void> {
    successMessage.value = '';
    setError('', 'error');
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

        const response = await axios.patch('/configuracion/datos-basicos', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const company = response.data?.company as Partial<CompanyData> | undefined;
        if (company) {
            Object.assign(companyInfo, company);
            savedCompanyState.value = { ...companyInfo };
        }

        resetLogoPreview();
        successMessage.value = response.data?.message ?? 'Los datos básicos se actualizaron correctamente.';
    } catch (error) {
        console.error('Error guardando datos básicos:', error);
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const validationErrors = error.response?.data?.errors as Record<string, string[]> | undefined;
            const message = validationErrors
                ? Object.values(validationErrors).flat().join(' ')
                : 'Revisa los campos e intenta nuevamente.';
            setError(message, 'warning');
        } else {
            setError('No se pudo guardar la información. Intenta nuevamente.', 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

onBeforeUnmount(() => {
    resetLogoPreview();
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

            <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
                <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-slate-100">
                            <img
                                :src="companyLogo"
                                alt="Logotipo de la empresa"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="flex-1">
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Empresa</p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ companyInfo.name || 'Sin nombre' }}</h2>
                            <p class="text-xs text-slate-500">NIT {{ companyInfo.nit || '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">
                            Secciones
                        </div>
                        <div class="space-y-2">
                            <template v-for="section in navSections" :key="section.label">
                                <Link
                                    v-if="section.href"
                                    :href="section.href"
                                    class="flex items-center justify-between gap-2 rounded-xl border px-3 py-2 text-left text-sm transition-colors"
                                    :class="section.active
                                        ? 'border-blue-100 bg-blue-50 text-blue-600'
                                        : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-800'"
                                    :aria-current="section.active ? 'page' : undefined"
                                >
                                    <div>
                                        <p class="font-semibold">{{ section.label }}</p>
                                        <p class="text-xs text-slate-400">{{ section.description }}</p>
                                    </div>
                                    <span
                                        v-if="section.active"
                                        class="text-[10px] font-semibold uppercase tracking-[0.3em] text-blue-600"
                                    >
                                        Activo
                                    </span>
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    disabled
                                    class="flex w-full items-center justify-between gap-2 rounded-xl border border-dashed border-slate-200 px-3 py-2 text-left text-sm text-slate-400 transition-colors"
                                    title="SecciÃ³n en desarrollo"
                                >
                                    <div>
                                        <p class="font-semibold">{{ section.label }}</p>
                                        <p class="text-xs text-slate-400">{{ section.description }}</p>
                                    </div>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.3em]">Próximo</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 text-slate-600">
                        <div
                            v-for="fact in quickFacts"
                            :key="fact.label"
                            class="rounded-2xl border border-gray-100 bg-slate-50 p-3"
                        >
                            <p class="text-[10px] uppercase tracking-[0.4em] text-slate-400">{{ fact.label }}</p>
                            <p class="text-sm font-semibold text-slate-900">{{ fact.value }}</p>
                        </div>
                    </div>
                </aside>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <form class="space-y-6" @submit.prevent="saveBasicData">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="legal-name" class="text-sm font-medium text-slate-700">Nombre legal</label>
                                <Input id="legal-name" v-model="companyInfo.name" />
                            </div>
                            <div class="space-y-2">
                                <label for="brand-name" class="text-sm font-medium text-slate-700">Nombre comercial</label>
                                <Input id="brand-name" v-model="companyInfo.brand" />
                            </div>
                            <div class="space-y-2">
                                <label for="document-type" class="text-sm font-medium text-slate-700">Tipo de documento</label>
                                <Input id="document-type" v-model="companyInfo.document_type" />
                            </div>
                            <div class="space-y-2">
                                <label for="nit" class="text-sm font-medium text-slate-700">Número de identificación</label>
                                <Input id="nit" v-model="companyInfo.nit" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="email" class="text-sm font-medium text-slate-700">Correo corporativo</label>
                                <Input id="email" v-model="companyInfo.email" />
                            </div>
                            <div class="space-y-2">
                                <label for="accounting-email" class="text-sm font-medium text-slate-700">Correo contable</label>
                                <Input id="accounting-email" v-model="companyInfo.accounting_email" />
                            </div>
                            <div class="space-y-2">
                                <label for="phone" class="text-sm font-medium text-slate-700">Teléfono principal</label>
                                <Input id="phone" v-model="companyInfo.phone" />
                            </div>
                            <div class="space-y-2">
                                <label for="website" class="text-sm font-medium text-slate-700">Sitio web</label>
                                <Input id="website" v-model="companyInfo.website" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="address" class="text-sm font-medium text-slate-700">Dirección</label>
                                <Input id="address" v-model="companyInfo.address" />
                            </div>
                            <div class="space-y-2">
                                <label for="city" class="text-sm font-medium text-slate-700">Ciudad</label>
                                <Input id="city" v-model="companyInfo.city" />
                            </div>
                            <div class="space-y-2">
                                <label for="country" class="text-sm font-medium text-slate-700">País</label>
                                <Input id="country" v-model="companyInfo.country" />
                            </div>
                            <div class="space-y-2">
                                <label for="industry" class="text-sm font-medium text-slate-700">Actividad económica</label>
                                <Input id="industry" v-model="companyInfo.industry" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="currency" class="text-sm font-medium text-slate-700">Moneda principal</label>
                            <Input id="currency" v-model="companyInfo.currency" />
                        </div>

                        <div class="space-y-2">
                            <label for="description" class="text-sm font-medium text-slate-700">Descripción</label>
                            <Textarea id="description" rows="4" v-model="companyInfo.description" />
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

                    <div class="mt-6 rounded-2xl border border-dashed border-gray-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Logotipo corporativo</p>
                                <p class="text-xs text-slate-500">
                                    Sube PNG, SVG o JPG de máximo 2 MB. Haz clic o arrastra una foto desde tu escritorio.
                                </p>
                            </div>
                            <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center">
                                <div class="flex flex-col items-center gap-2">
                                    <div
                                        class="relative flex h-32 w-32 cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-gray-200 bg-slate-50 transition hover:border-blue-400 sm:h-28 sm:w-28"
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
                                            <div class="absolute inset-x-0 bottom-0 bg-black/60 py-1 text-[10px] uppercase tracking-[0.3em] text-white">
                                                {{ hasCustomLogo ? 'Listo para subir' : 'Sin cambios' }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="flex flex-col items-center gap-1 text-xs text-slate-400">
                                                <Image class="h-5 w-5" />
                                                <span class="text-xs text-center leading-tight">
                                                    Haz clic o arrastra una imagen.
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="triggerLogoUpload">
                                        Cambiar icono
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="!hasCustomLogo"
                                        @click="resetLogoPreview"
                                    >
                                        Restablecer
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <input
                            ref="iconInputRef"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleLogoChange"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
