<script setup lang="ts">
import AccountChartDialog from '@/pages/Finance/AccountChart/AccountChartDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { AccountingAccountListItem } from '@/domain/Finance';
import { computed, ref } from 'vue';

type PageMode = 'create' | 'edit' | 'show';

const props = defineProps<{
    mode: PageMode;
    account?: AccountingAccountListItem | null;
}>();

const ROUTES = {
    index: '/finanzas/catalogo-cuentas',
} as const;

const dialogOpen = ref(true);

const dialogMode = computed<'create' | 'edit' | 'show'>(() => props.mode);
const pageTitle = computed(() => {
    if (props.mode === 'create') return 'Nueva cuenta';
    if (props.mode === 'edit') return 'Editar cuenta';
    return 'Cuenta contable';
});

const breadcrumbs = computed(() => [
    { title: 'Catalogo de cuentas', href: ROUTES.index },
    { title: pageTitle.value, href: ROUTES.index },
]);

function closeWrapper(): void {
    router.visit(ROUTES.index);
}

function handleDialogOpenChange(open: boolean): void {
    dialogOpen.value = open;
    if (!open) {
        closeWrapper();
    }
}

function handleSaved(): void {
    closeWrapper();
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[40vh]">
            <AccountChartDialog
                :open="dialogOpen"
                :mode="dialogMode"
                :account="props.account ?? null"
                :account-id="props.account?.id ?? null"
                @update:open="handleDialogOpenChange"
                @saved="handleSaved"
            />
        </div>
    </AppLayout>
</template>
