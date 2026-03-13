<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import { createAccountingAccount, updateAccountingAccount, type AccountingAccountPayload, type AccountType } from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type PageMode = 'create' | 'show' | 'edit';

interface SelectOption {
    id: string | number;
    name: string;
}

interface AccountProp {
    id: number;
    code: string;
    name: string;
    account_type: AccountType;
    parent_id: number | null;
    level: number;
    allows_entries: boolean;
    is_active: boolean;
    special_type: string | null;
    description: string | null;
    parent?: { id: number; code: string; name: string } | null;
}

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    mode: PageMode;
    account?: AccountProp;
    accountTypeOptions: SelectOption[];
    parentOptions: SelectOption[];
    specialTypeOptions: SelectOption[];
}>();

// ─── Constants ────────────────────────────────────────────────────────────────

const ROUTES = {
    index: '/finanzas/catalogo-cuentas',
    show: (id: number) => `/finanzas/catalogo-cuentas/${id}`,
    edit: (id: number) => `/finanzas/catalogo-cuentas/${id}/editar`,
} as const;

const isCreate = computed(() => props.mode === 'create');
const isShow = computed(() => props.mode === 'show');
const isEdit = computed(() => props.mode === 'edit');
const isReadOnly = computed(() => props.mode === 'show');
const pageTitle = computed(() => {
    if (isCreate.value) return 'Nueva cuenta';
    if (isEdit.value) return `Editar cuenta: ${props.account?.code ?? ''}`;
    return `Cuenta: ${props.account?.code ?? ''}`;
});

const breadcrumbs = computed(() => [
    { title: 'Catálogo de cuentas', href: ROUTES.index },
    { title: pageTitle.value, href: '#' },
]);

// ─── Alerts ───────────────────────────────────────────────────────────────────

const { successMessage, errorMessage, errorType, success, error, clear } = useGlobalAlert();

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref<{
    code: string;
    name: string;
    account_type: string;
    parent_id: string | number;
    allows_entries: boolean;
    is_active: boolean;
    special_type: string;
    description: string;
}>({
    code: props.account?.code ?? '',
    name: props.account?.name ?? '',
    account_type: props.account?.account_type ?? '',
    parent_id: props.account?.parent_id ?? '',
    allows_entries: props.account?.allows_entries ?? true,
    is_active: props.account?.is_active ?? true,
    special_type: props.account?.special_type ?? '',
    description: props.account?.description ?? '',
});

const validationErrors = ref<Record<string, string>>({});
const isSaving = ref(false);

// ─── Parent options with "Ninguna" ────────────────────────────────────────────

const parentOptionsWithNone = computed<SelectOption[]>(() => [
    { id: '', name: '— Sin cuenta padre —' },
    ...props.parentOptions,
]);

// ─── Validation ───────────────────────────────────────────────────────────────

function validate(): boolean {
    const errors: Record<string, string> = {};

    if (!form.value.code.trim()) {
        errors.code = 'El código es obligatorio.';
    }
    if (!form.value.name.trim()) {
        errors.name = 'El nombre es obligatorio.';
    }
    if (!form.value.account_type) {
        errors.account_type = 'El tipo de cuenta es obligatorio.';
    }

    validationErrors.value = errors;
    return Object.keys(errors).length === 0;
}

// ─── Actions ──────────────────────────────────────────────────────────────────

function buildPayload(): AccountingAccountPayload {
    return {
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        account_type: form.value.account_type as AccountType,
        parent_id: form.value.parent_id ? Number(form.value.parent_id) : null,
        allows_entries: form.value.allows_entries,
        is_active: form.value.is_active,
        special_type: form.value.special_type || null,
        description: form.value.description.trim() || null,
    };
}

async function handleSave(): Promise<void> {
    if (!validate()) return;

    isSaving.value = true;
    try {
        const payload = buildPayload();

        if (isCreate.value) {
            await createAccountingAccount(payload);
            success('Cuenta creada correctamente');
            router.visit(ROUTES.index);
        } else if (isEdit.value && props.account) {
            await updateAccountingAccount(props.account.id, payload);
            success('Cuenta actualizada correctamente');
            router.visit(ROUTES.index);
        }
    } catch (err: any) {
        const serverErrors = err?.response?.data?.errors;
        if (serverErrors) {
            validationErrors.value = {};
            for (const [key, messages] of Object.entries(serverErrors)) {
                validationErrors.value[key] = Array.isArray(messages) ? messages[0] : String(messages);
            }
            error('Por favor corrige los errores del formulario.');
        } else {
            error(err?.response?.data?.message ?? 'Error al guardar la cuenta.');
        }
    } finally {
        isSaving.value = false;
    }
}

function handleCancel(): void {
    router.visit(ROUTES.index);
}

function handleEdit(): void {
    if (props.account) {
        router.visit(ROUTES.edit(props.account.id));
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <!-- Alerts -->
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />

            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">{{ pageTitle }}</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        <template v-if="isCreate">Define los datos de la nueva cuenta contable.</template>
                        <template v-else-if="isEdit">Modifica los datos de la cuenta contable.</template>
                        <template v-else>Detalles de la cuenta contable.</template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="pill" @click="handleCancel">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Volver
                    </Button>
                    <Button v-if="isShow" type="button" size="pill" @click="handleEdit">
                        Editar
                    </Button>
                    <Button
                        v-if="!isShow"
                        type="button"
                        size="pill"
                        :disabled="isSaving"
                        @click="handleSave"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
            </div>

            <!-- Form -->
            <div :class="uiPatterns.pageIndex.tableWrap">
                <div class="p-6">
                    <div class="mx-auto max-w-3xl space-y-6">

                        <!-- Row: Código + Nombre -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label class="text-sm font-medium">
                                    Código <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    v-model="form.code"
                                    placeholder="Ej: 1105"
                                    :disabled="isReadOnly"
                                    :class="{ 'border-destructive': validationErrors.code }"
                                />
                                <p v-if="validationErrors.code" class="text-xs text-destructive">
                                    {{ validationErrors.code }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-sm font-medium">
                                    Nombre <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    v-model="form.name"
                                    placeholder="Ej: Caja general"
                                    :disabled="isReadOnly"
                                    :class="{ 'border-destructive': validationErrors.name }"
                                />
                                <p v-if="validationErrors.name" class="text-xs text-destructive">
                                    {{ validationErrors.name }}
                                </p>
                            </div>
                        </div>

                        <!-- Row: Tipo de cuenta + Cuenta padre -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label class="text-sm font-medium">
                                    Tipo de cuenta <span class="text-destructive">*</span>
                                </Label>
                                <SelectField
                                    v-model="form.account_type"
                                    :options="accountTypeOptions"
                                    placeholder="Seleccionar tipo"
                                    :disabled="isReadOnly"
                                    searchable
                                />
                                <p v-if="validationErrors.account_type" class="text-xs text-destructive">
                                    {{ validationErrors.account_type }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-sm font-medium">Cuenta padre</Label>
                                <SelectField
                                    v-model="form.parent_id"
                                    :options="parentOptionsWithNone"
                                    placeholder="Sin cuenta padre"
                                    :disabled="isReadOnly"
                                    searchable
                                />
                                <p v-if="validationErrors.parent_id" class="text-xs text-destructive">
                                    {{ validationErrors.parent_id }}
                                </p>
                            </div>
                        </div>

                        <!-- Row: Uso especial -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label class="text-sm font-medium">Uso especial</Label>
                                <SelectField
                                    v-model="form.special_type"
                                    :options="specialTypeOptions"
                                    placeholder="Ninguno"
                                    :disabled="isReadOnly"
                                />
                            </div>
                        </div>

                        <!-- Row: Switches -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="flex items-center gap-3">
                                <Switch
                                    :checked="form.allows_entries"
                                    :disabled="isReadOnly"
                                    @update:checked="(val: boolean) => (form.allows_entries = val)"
                                />
                                <Label class="text-sm font-medium">Permite movimientos</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch
                                    :checked="form.is_active"
                                    :disabled="isReadOnly"
                                    @update:checked="(val: boolean) => (form.is_active = val)"
                                />
                                <Label class="text-sm font-medium">Cuenta activa</Label>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium">Descripción</Label>
                            <Textarea
                                v-model="form.description"
                                placeholder="Descripción opcional de la cuenta"
                                :disabled="isReadOnly"
                                rows="3"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
