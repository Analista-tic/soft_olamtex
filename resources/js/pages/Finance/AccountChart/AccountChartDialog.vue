<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    createAccountingAccount,
    fetchAccountingAccount,
    fetchAccountingAccountOptions,
    updateAccountingAccount,
    type AccountType,
    type AccountingAccountListItem,
    type AccountingAccountPayload,
} from '@/domain/Finance';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    buildParentAccountOptions,
    type HierarchicalAccountOption,
} from '@/domain/Finance/accountHierarchy';
import {
    getHierarchicalAccountOptionLabelClass,
    getHierarchicalAccountOptionRowClass,
} from '@/lib/accountHierarchyUi';

type DialogMode = 'create' | 'edit' | 'show';

type FormState = {
    code: string;
    name: string;
    account_type: string;
    parent_id: number | '';
    allows_entries: boolean;
    is_active: boolean;
    special_type: string;
    description: string;
};

const props = withDefaults(defineProps<{
    open: boolean;
    mode: DialogMode;
    account?: AccountingAccountListItem | null;
    accountId?: number | null;
    parentAccountId?: number | null;
}>(), {
    account: null,
    accountId: null,
    parentAccountId: null,
});

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'saved', payload: { mode: 'create' | 'edit'; account: AccountingAccountListItem }): void;
}>();

const ACCOUNT_TYPE_OPTIONS: Array<{ id: AccountType; name: string }> = [
    { id: 'asset', name: 'Activo' },
    { id: 'liability', name: 'Pasivo' },
    { id: 'equity', name: 'Patrimonio' },
    { id: 'income', name: 'Ingreso' },
    { id: 'cost', name: 'Costo' },
    { id: 'expense', name: 'Gasto' },
];

const SPECIAL_TYPE_OPTIONS = [
    { id: '', name: 'Ninguno' },
    { id: 'cash', name: 'Caja' },
    { id: 'bank', name: 'Banco' },
    { id: 'accounts_payable', name: 'Cuentas por pagar' },
    { id: 'accounts_receivable', name: 'Cuentas por cobrar' },
];

const internalOpen = ref(props.open);
const isInitializing = ref(false);
const isSaving = ref(false);
const submitError = ref('');
const validationErrors = ref<Record<string, string>>({});
const currentAccount = ref<AccountingAccountListItem | null>(null);
const parentOptions = ref<HierarchicalAccountOption[]>([]);
const loadRequestId = ref(0);

const form = ref<FormState>(buildDefaultForm());

const isCreate = computed(() => props.mode === 'create');
const isEdit = computed(() => props.mode === 'edit');
const isShow = computed(() => props.mode === 'show');
const isReadOnly = computed(() => isShow.value);
const isSubcreate = computed(() => isCreate.value && props.parentAccountId !== null);
const isBusy = computed(() => isInitializing.value || isSaving.value);

const dialogTitle = computed(() => {
    if (isSubcreate.value) return 'Nueva subcuenta';
    if (isCreate.value) return 'Nueva cuenta';
    if (isEdit.value) return 'Editar cuenta';
    return 'Cuenta contable';
});

const dialogDescription = computed(() => {
    if (isSubcreate.value) return 'Crea una nueva subcuenta dentro del arbol contable actual.';
    if (isCreate.value) return 'Registra una nueva cuenta contable sin salir del catalogo.';
    if (isEdit.value) return 'Actualiza la configuracion de la cuenta contable seleccionada.';
    return 'Consulta los datos de la cuenta contable.';
});

const dialogActionLabel = computed(() => {
    if (isSaving.value) return 'Guardando...';
    return 'Guardar';
});

const parentContextLabel = computed(() => {
    const parent = parentOptions.value.find((option) => option.id === form.value.parent_id);
    return parent?.name ?? null;
});

const standardSelectButtonClass =
    'h-11 w-full rounded-xl border border-border/60 bg-background px-3.5 pr-10 text-sm text-foreground transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/35 focus-visible:ring-offset-2 focus-visible:ring-offset-background';

watch(
    () => props.open,
    (isOpen) => {
        internalOpen.value = isOpen;

        if (!isOpen) {
            resetDialogState();
            return;
        }

        void initializeDialog();
    },
    { immediate: true },
);

watch(
    () => [props.mode, props.accountId, props.parentAccountId, props.account?.id] as const,
    () => {
        if (props.open) {
            void initializeDialog();
        }
    },
);

watch(
    () => internalOpen.value,
    (value) => {
        if (value !== props.open) {
            emit('update:open', value);
        }
    },
);

function buildDefaultForm(): FormState {
    return {
        code: '',
        name: '',
        account_type: '',
        parent_id: props.parentAccountId ?? '',
        allows_entries: true,
        is_active: true,
        special_type: '',
        description: '',
    };
}

function buildFormFromAccount(account: AccountingAccountListItem | null): FormState {
    if (!account) {
        return buildDefaultForm();
    }

    return {
        code: account.code ?? '',
        name: account.name ?? '',
        account_type: account.account_type ?? '',
        parent_id: account.parent_id ?? '',
        allows_entries: Boolean(account.allows_entries),
        is_active: Boolean(account.is_active),
        special_type: account.special_type ?? '',
        description: account.description ?? '',
    };
}

function resetDialogState(): void {
    loadRequestId.value += 1;
    form.value = buildDefaultForm();
    validationErrors.value = {};
    submitError.value = '';
    isInitializing.value = false;
    isSaving.value = false;
    currentAccount.value = null;
    parentOptions.value = [];
}

async function resolveAccount(): Promise<AccountingAccountListItem | null> {
    if (props.account) {
        return props.account;
    }

    if ((isEdit.value || isShow.value) && props.accountId) {
        return fetchAccountingAccount(props.accountId);
    }

    return null;
}

async function initializeDialog(): Promise<void> {
    const requestId = ++loadRequestId.value;
    isInitializing.value = true;
    submitError.value = '';
    validationErrors.value = {};

    try {
        const [account, options] = await Promise.all([
            resolveAccount(),
            fetchAccountingAccountOptions(false),
        ]);

        if (requestId !== loadRequestId.value) return;

        currentAccount.value = account;
        parentOptions.value = buildParentAccountOptions(options, account?.id ?? null);
        form.value = buildFormFromAccount(account);

        if (!account && props.parentAccountId !== null) {
            form.value.parent_id = props.parentAccountId;
        }
    } catch (err) {
        if (requestId !== loadRequestId.value) return;
        submitError.value = 'No fue posible cargar el formulario de cuenta contable.';
        console.error('Error al inicializar dialogo de cuenta contable', err);
    } finally {
        if (requestId === loadRequestId.value) {
            isInitializing.value = false;
        }
    }
}

function validateForm(): boolean {
    const errors: Record<string, string> = {};

    if (!form.value.code.trim()) {
        errors.code = 'El codigo es obligatorio.';
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

function buildPayload(): AccountingAccountPayload {
    return {
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        account_type: form.value.account_type as AccountType,
        parent_id: form.value.parent_id === '' ? null : Number(form.value.parent_id),
        allows_entries: form.value.allows_entries,
        is_active: form.value.is_active,
        special_type: form.value.special_type || null,
        description: form.value.description.trim() || null,
    };
}

async function handleSave(): Promise<void> {
    if (isReadOnly.value || !validateForm()) return;

    isSaving.value = true;
    submitError.value = '';

    try {
        const payload = buildPayload();
        let savedAccount: AccountingAccountListItem;

        if (isCreate.value) {
            savedAccount = await createAccountingAccount(payload);
        } else {
            const targetAccountId = currentAccount.value?.id ?? props.accountId;

            if (targetAccountId === null) {
                submitError.value = 'No fue posible identificar la cuenta que se debe actualizar.';
                return;
            }

            savedAccount = await updateAccountingAccount(targetAccountId, payload);
        }

        emit('saved', {
            mode: isCreate.value ? 'create' : 'edit',
            account: savedAccount,
        });
        internalOpen.value = false;
    } catch (err: any) {
        const serverErrors = err?.response?.data?.errors;

        if (serverErrors) {
            const normalizedErrors: Record<string, string> = {};
            for (const [key, messages] of Object.entries(serverErrors)) {
                normalizedErrors[key] = Array.isArray(messages) ? String(messages[0]) : String(messages);
            }
            validationErrors.value = normalizedErrors;
            submitError.value = err?.response?.data?.message ?? 'Corrige los errores del formulario.';
        } else {
            submitError.value = err?.response?.data?.message ?? 'No fue posible guardar la cuenta contable.';
        }
    } finally {
        isSaving.value = false;
    }
}

function handleCancel(): void {
    if (isSaving.value) return;
    internalOpen.value = false;
}

</script>

<template>
    <Dialog v-model:open="internalOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">
                    {{ dialogTitle }}
                </DialogTitle>
                <DialogDescription class="max-w-[62ch] text-sm leading-6 text-muted-foreground">
                    {{ dialogDescription }}
                </DialogDescription>
                <p v-if="isSubcreate && parentContextLabel" class="pt-1 text-xs leading-5 text-muted-foreground">
                    Se creara como una subcuenta de {{ parentContextLabel }}.
                </p>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin" :class="{ 'min-h-[220px]': isInitializing }">
                <FormLoadingOverlay
                    :active="isInitializing"
                    :delay-ms="0"
                    message="Cargando cuenta contable..."
                    description="Preparando la información de la cuenta."
                />
                <div v-if="submitError" class="mb-5 rounded-2xl border border-destructive/10 bg-destructive/[0.02] px-4 py-3 text-sm text-destructive">
                    {{ submitError }}
                </div>

                <div v-if="isInitializing" class="min-h-[220px]" />

                <div v-else class="space-y-6">
                    <div class="space-y-1.5">
                        <Label class="text-sm font-medium text-foreground/90">
                            Nombre <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="form.name"
                            placeholder="Ej: Caja general"
                            :disabled="isBusy || isReadOnly"
                            class="h-11 rounded-xl border-border/70 shadow-none focus-visible:ring-ring/40"
                            :class="{ 'border-destructive': validationErrors.name }"
                        />
                        <p v-if="validationErrors.name" class="text-xs text-destructive">
                            {{ validationErrors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium text-foreground/90">
                                Codigo <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                v-model="form.code"
                                placeholder="Ej: 1105"
                                :disabled="isBusy || isReadOnly"
                                class="h-11 rounded-xl border-border/70 shadow-none focus-visible:ring-ring/40"
                                :class="{ 'border-destructive': validationErrors.code }"
                            />
                            <p v-if="validationErrors.code" class="text-xs text-destructive">
                                {{ validationErrors.code }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium text-foreground/90">
                                Tipo de cuenta <span class="text-destructive">*</span>
                            </Label>
                            <SelectField
                                v-model="form.account_type"
                                :options="ACCOUNT_TYPE_OPTIONS"
                                placeholder="Seleccionar tipo"
                                searchable
                                :disabled="isBusy || isReadOnly"
                                :button-class="standardSelectButtonClass"
                            />
                            <p v-if="validationErrors.account_type" class="text-xs text-destructive">
                                {{ validationErrors.account_type }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium text-foreground/90">Cuenta padre</Label>
                            <SelectField
                                v-model="form.parent_id"
                                :options="parentOptions"
                                placeholder="Sin cuenta padre"
                                searchable
                                :disabled="isBusy || isReadOnly"
                                :button-class="standardSelectButtonClass"
                                options-class="py-1.5"
                                :get-option-class="getHierarchicalAccountOptionRowClass"
                                :get-option-label-class="getHierarchicalAccountOptionLabelClass"
                            />
                            <p v-if="validationErrors.parent_id" class="text-xs text-destructive">
                                {{ validationErrors.parent_id }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium text-foreground/90">Uso especial</Label>
                            <SelectField
                                v-model="form.special_type"
                                :options="SPECIAL_TYPE_OPTIONS"
                                placeholder="Ninguno"
                                :disabled="isBusy || isReadOnly"
                                :button-class="standardSelectButtonClass"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-sm font-medium text-foreground/90">Descripcion</Label>
                        <Textarea
                            v-model="form.description"
                            placeholder="Descripcion opcional de la cuenta"
                            rows="6"
                            :disabled="isBusy || isReadOnly"
                            class="min-h-[144px] rounded-2xl border border-border/60 bg-background px-3.5 py-3 text-sm leading-6 shadow-none focus-visible:ring-2 focus-visible:ring-ring/35"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-x-8 gap-y-3 pt-1 md:grid-cols-2">
                        <div class="flex items-center justify-between gap-4 py-1">
                            <Label class="text-sm font-medium text-foreground/90">Permite movimientos</Label>
                            <Switch
                                :checked="form.allows_entries"
                                :disabled="isBusy || isReadOnly"
                                @update:checked="(value: boolean) => (form.allows_entries = value)"
                            />
                        </div>

                        <div class="flex items-center justify-between gap-4 py-1">
                            <Label class="text-sm font-medium text-foreground/90">Cuenta activa</Label>
                            <Switch
                                :checked="form.is_active"
                                :disabled="isBusy || isReadOnly"
                                @update:checked="(value: boolean) => (form.is_active = value)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-3 border-t border-border px-5 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="min-w-[128px] justify-center rounded-full border-border/70 bg-background px-5 text-sm font-semibold"
                    :disabled="isSaving"
                    @click="handleCancel"
                >
                    {{ isShow ? 'Cerrar' : 'Cancelar' }}
                </Button>
                <Button
                    v-if="!isShow"
                    type="button"
                    size="pill"
                    class="min-w-[148px] justify-center rounded-full px-6 text-sm font-semibold shadow-sm"
                    :disabled="isBusy"
                    @click="handleSave"
                >
                    <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                    {{ dialogActionLabel }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
