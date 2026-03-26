<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import MetricTile from '@/components/MetricTile.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import type { BankAccount, BankAccountFormData, BankAccountSummary, BankAccountType } from '@/domain/Finance/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Banknote, CreditCard, Landmark, Pencil, Plus, Search, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ━━━━━━ PROPS ━━━━━━
const props = defineProps<{
    bankAccounts: BankAccount[];
    summary: BankAccountSummary;
}>();

// ━━━━━━ CONSTANTS ━━━━━━
const breadcrumbs = [{ title: 'Bancos y Cajas', href: '/finanzas/bancos' }];

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}

const typeLabels: Record<BankAccountType, string> = {
    bank: 'Banco',
    cash: 'Efectivo',
    credit_card: 'Tarjeta de crédito',
};

const typeIcons: Record<BankAccountType, typeof Landmark> = {
    bank: Landmark,
    cash: Banknote,
    credit_card: CreditCard,
};

const typeOptions = [
    { id: 'bank', name: 'Banco' },
    { id: 'cash', name: 'Efectivo' },
    { id: 'credit_card', name: 'Tarjeta de crédito' },
];

// ━━━━━━ ALERTS ━━━━━━
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

// ━━━━━━ SEARCH ━━━━━━
const searchQuery = ref('');

const filteredAccounts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return props.bankAccounts;
    return props.bankAccounts.filter((a) => a.name.toLowerCase().includes(query));
});

// ━━━━━━ DIALOG STATE ━━━━━━
const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit'>('create');
const editingAccount = ref<BankAccount | null>(null);
const isSubmitting = ref(false);

function createEmptyForm(): BankAccountFormData {
    return {
        name: '',
        type: 'bank',
        bank_name: '',
        account_number: '',
        currency: 'COP',
        opening_balance: null,
        opening_balance_date: null,
        description: '',
    };
}

const form = ref<BankAccountFormData>(createEmptyForm());
const formIsActive = ref(true);

function openCreateDialog() {
    dialogMode.value = 'create';
    editingAccount.value = null;
    form.value = createEmptyForm();
    formIsActive.value = true;
    isDialogOpen.value = true;
}

function openEditDialog(account: BankAccount) {
    dialogMode.value = 'edit';
    editingAccount.value = account;
    form.value = {
        name: account.name,
        type: account.type,
        bank_name: account.bank_name ?? '',
        account_number: account.account_number ?? '',
        currency: account.currency,
        opening_balance: account.opening_balance,
        opening_balance_date: account.opening_balance_date,
        description: account.description ?? '',
    };
    formIsActive.value = account.is_active;
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
    editingAccount.value = null;
}

async function handleSubmit() {
    if (!form.value.name.trim()) {
        errorType.value = 'warning';
        errorMessage.value = 'El nombre de la cuenta es obligatorio.';
        return;
    }

    isSubmitting.value = true;

    try {
        if (dialogMode.value === 'create') {
            await axios.post('/finanzas/bancos', {
                name: form.value.name.trim(),
                type: form.value.type,
                bank_name: form.value.bank_name?.trim() || null,
                account_number: form.value.account_number?.trim() || null,
                currency: form.value.currency || 'COP',
                opening_balance: form.value.opening_balance ?? 0,
                opening_balance_date: form.value.opening_balance_date || null,
                description: form.value.description?.trim() || null,
            });
            successMessage.value = 'Cuenta bancaria creada correctamente.';
        } else if (editingAccount.value) {
            await axios.put(`/finanzas/bancos/${editingAccount.value.id}`, {
                name: form.value.name.trim(),
                bank_name: form.value.bank_name?.trim() || null,
                account_number: form.value.account_number?.trim() || null,
                description: form.value.description?.trim() || null,
                is_active: formIsActive.value,
            });
            successMessage.value = 'Cuenta bancaria actualizada correctamente.';
        }
        closeDialog();
        router.reload();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        errorType.value = 'error';
        errorMessage.value = axiosErr.response?.data?.message ?? 'Error al guardar la cuenta.';
    } finally {
        isSubmitting.value = false;
    }
}

// ━━━━━━ DEACTIVATE CONFIRM ━━━━━━
const isDeactivateDialogOpen = ref(false);
const accountToToggle = ref<BankAccount | null>(null);

function confirmToggleStatus(account: BankAccount) {
    accountToToggle.value = account;
    isDeactivateDialogOpen.value = true;
}

async function handleToggleStatus() {
    if (!accountToToggle.value) return;

    const account = accountToToggle.value;
    const newStatus = !account.is_active;

    isDeactivateDialogOpen.value = false;
    accountToToggle.value = null;

    try {
        await axios.put(`/finanzas/bancos/${account.id}`, {
            name: account.name,
            bank_name: account.bank_name,
            account_number: account.account_number,
            description: account.description,
            is_active: newStatus,
        });
        successMessage.value = newStatus
            ? 'Cuenta activada correctamente.'
            : 'Cuenta desactivada correctamente.';
        router.reload();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        errorType.value = 'error';
        errorMessage.value = axiosErr.response?.data?.message ?? 'Error al cambiar el estado.';
    }
}

// ━━━━━━ NAVIGATION ━━━━━━
function openShow(account: BankAccount) {
    router.visit(`/finanzas/bancos/${account.id}`);
}

// ━━━━━━ DATE DISPLAY ━━━━━━
const todayFormatted = new Date().toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});
</script>

<template>
    <Head title="Bancos y Cajas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />

        <div :class="uiPatterns.pageIndex.page">
            <!-- PAGE HEADER -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Bancos y Cajas</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        Administra tus cuentas bancarias, cajas de efectivo y tarjetas de crédito.
                    </p>
                </div>
                <Button type="button" size="pill" @click="openCreateDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Agregar cuenta
                </Button>
            </div>

            <!-- KPI CARDS -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <MetricTile
                    label="Saldo en bancos y efectivo"
                    :value="formatCurrency(props.summary.totalBankCash)"
                    :show-badge="false"
                    :value-class="props.summary.totalBankCash >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                    dense
                >
                    <template #default>
                        <p class="mt-1 text-xs text-muted-foreground">Última actualización: {{ todayFormatted }}</p>
                    </template>
                </MetricTile>
                <MetricTile
                    label="Deuda en tarjetas de crédito"
                    :value="formatCurrency(props.summary.totalCreditCardDebt)"
                    :show-badge="false"
                    value-class="text-red-600 dark:text-red-400"
                    dense
                >
                    <template #default>
                        <p class="mt-1 text-xs text-muted-foreground">Última actualización: {{ todayFormatted }}</p>
                    </template>
                </MetricTile>
            </div>

            <!-- SEARCH BAR -->
            <div class="flex items-center gap-3">
                <div class="relative min-w-[260px] flex-1">
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar por nombre de cuenta"
                        class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                    />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>

            <!-- TABLE -->
            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Nombre</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Tipo de cuenta</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Número de cuenta</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Saldo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <!-- EMPTY STATE -->
                        <TableRow v-if="filteredAccounts.length === 0">
                            <TableCell colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <Landmark class="h-10 w-10 text-muted-foreground/40" />
                                    <p class="text-sm text-muted-foreground">
                                        {{ searchQuery ? 'No se encontraron cuentas con ese nombre.' : 'No hay cuentas registradas.' }}
                                    </p>
                                    <Button v-if="!searchQuery" type="button" size="pill" variant="outline" @click="openCreateDialog">
                                        <Plus class="mr-2 h-4 w-4" />
                                        Crear primera cuenta
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>

                        <!-- DATA ROWS -->
                        <TableRow
                            v-for="account in filteredAccounts"
                            :key="account.id"
                            :class="{ 'opacity-50': !account.is_active }"
                        >
                            <TableCell :class="uiPatterns.typography.bodyStrong">{{ account.name }}</TableCell>
                            <TableCell>
                                <span class="inline-flex items-center gap-1.5 text-sm text-foreground">
                                    <component :is="typeIcons[account.type]" class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ typeLabels[account.type] }}
                                </span>
                            </TableCell>
                            <TableCell>{{ account.account_number || '—' }}</TableCell>
                            <TableCell>
                                <TableStatusBadge
                                    :label="account.is_active ? 'Activo' : 'Inactivo'"
                                    :tone="account.is_active ? 'green' : 'gray'"
                                />
                            </TableCell>
                            <TableCell>
                                <span
                                    class="font-semibold"
                                    :class="account.current_balance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                >
                                    {{ formatCurrency(account.current_balance) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :on-click="() => openShow(account)" />
                                    <TableActionsMenu content-class="w-48">
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openEditDialog(account)">
                                            <Pencil class="h-4 w-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="confirmToggleStatus(account)">
                                            <component
                                                :is="account.is_active ? ToggleLeft : ToggleRight"
                                                class="h-4 w-4"
                                            />
                                            {{ account.is_active ? 'Desactivar' : 'Activar' }}
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- CREATE / EDIT DIALOG -->
        <Dialog :open="isDialogOpen" @update:open="(v) => { if (!v) closeDialog(); }">
            <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>
                        {{ dialogMode === 'create' ? 'Crear cuenta' : 'Editar cuenta' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ dialogMode === 'create'
                            ? 'Completa los campos para registrar una nueva cuenta bancaria o de efectivo.'
                            : 'Modifica los datos de la cuenta seleccionada.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 px-1 pt-2">
                    <!-- Row 1: Name + Type -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <InputField
                            v-model="form.name"
                            label="Nombre de la cuenta"
                            placeholder="Ej: Bancolombia Ahorros"
                            required
                        />
                        <SelectField
                            v-model="form.type"
                            label="Tipo de cuenta"
                            :options="typeOptions"
                            required
                            :disabled="dialogMode === 'edit'"
                        />
                    </div>

                    <!-- Row 2: Bank name + Account number -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <InputField
                            v-if="form.type === 'bank'"
                            v-model="form.bank_name"
                            label="Nombre del banco"
                            placeholder="Ej: Bancolombia"
                        />
                        <InputField
                            v-model="form.account_number"
                            label="Número de cuenta"
                            placeholder="Ej: 001-234567-89"
                            :class="{ 'sm:col-span-2': form.type !== 'bank' }"
                        />
                    </div>

                    <!-- Row 3: Opening balance + Date (only on create) -->
                    <div v-if="dialogMode === 'create'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <InputField
                            v-model="form.opening_balance"
                            label="Saldo inicial"
                            type="currency"
                            :decimals="0"
                            placeholder="0"
                        />
                        <InputDate
                            v-model="form.opening_balance_date"
                            label="Fecha de saldo inicial"
                            placeholder="Selecciona una fecha"
                            :clearable="true"
                            :show-today="true"
                        />
                    </div>

                    <!-- Row 4: Description -->
                    <InputField
                        v-model="form.description"
                        label="Descripción"
                        placeholder="Notas adicionales sobre esta cuenta"
                    />
                </div>

                <DialogFooter class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="pill" @click="closeDialog">
                        Cancelar
                    </Button>
                    <Button
                        size="pill"
                        :disabled="isSubmitting"
                        class="shadow-sm"
                        @click="handleSubmit"
                    >
                        {{ dialogMode === 'create' ? 'Guardar' : 'Actualizar' }}
                    </Button>
                </DialogFooter>
            </FormDialogContent>
        </Dialog>

        <!-- CONFIRM DEACTIVATE/ACTIVATE -->
        <ConfirmDialog
            v-model:open="isDeactivateDialogOpen"
            :title="accountToToggle?.is_active ? 'Desactivar cuenta' : 'Activar cuenta'"
            :description="
                accountToToggle?.is_active
                    ? `¿Estás seguro de que deseas desactivar la cuenta «${accountToToggle?.name}»? No se eliminará, pero dejará de aparecer como activa.`
                    : `¿Deseas reactivar la cuenta «${accountToToggle?.name}»?`
            "
            :confirm-text="accountToToggle?.is_active ? 'Desactivar' : 'Activar'"
            cancel-text="Cancelar"
            :destructive="accountToToggle?.is_active ?? false"
            @confirm="handleToggleStatus"
        />
    </AppLayout>
</template>
