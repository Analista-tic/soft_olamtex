<script setup lang="ts">
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import type { Item } from '@/domain/MasterData';
import AppLayout from '@/layouts/AppLayout.vue';
import { ITEM_FORM_DRAFT_KEY } from '@/lib/storageKeys';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import ItemFormContent from './ItemFormContent.vue';

const breadcrumbs = [
    { title: 'Catalogo de item', href: route('listado') },
    { title: 'Formulario avanzado', href: '' },
];

const props = defineProps<{
    initialForm?: Partial<Item>;
}>();

const form = ref<Partial<Item>>(props.initialForm ?? {});
const isLoading = ref(false);
const returnToUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get('return_to') || '';
});
const returnToItemParam = computed(() => {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get('select_item') || '';
});

onMounted(() => {
    if (!props.initialForm) {
        const draft = localStorage.getItem(ITEM_FORM_DRAFT_KEY);
        if (draft) {
            try {
                form.value = JSON.parse(draft);
            } catch {
                form.value = {};
            }
            localStorage.removeItem(ITEM_FORM_DRAFT_KEY);
        }
    }
});

watch(
    () => props.initialForm,
    (value) => {
        form.value = value ?? {};
    },
);

const dialogType = computed(() => (form.value.id ? 'edit' : 'create'));

function handleClose() {
    if (returnToUrl.value) {
        router.visit(returnToUrl.value);
        return;
    }
    window.location.href = route('listado');
}

function handleSaved(mode: 'create' | 'edit') {
    if (returnToUrl.value) {
        const url = new URL(returnToUrl.value, window.location.origin);
        if (mode === 'create' && form.value.id) {
            url.searchParams.set(returnToItemParam.value || 'created_item_id', String(form.value.id));
        }
        router.visit(url.toString());
        return;
    }
    window.location.href = route('listado');
}

function handleRefresh() {
    /* no-op: the page stays on the advanced form */
}

function handleError(message: string) {
    console.error('Advanced form error:', message);
}

function handleLoadingState(value: boolean) {
    isLoading.value = value;
}
</script>

<template>
    <Head title="Formulario avanzado de items" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FormLoadingOverlay :active="isLoading" fullscreen message="Cargando formulario avanzado..." />

        <section class="flex w-full justify-center p-2">
            <div class="w-full max-w-6xl rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border sm:p-8">
                <h1 class="text-lg font-semibold text-card-foreground">Formulario avanzado</h1>
                <p class="mb-4 text-sm text-muted-foreground">Aqui puedes completar todos los campos del item sin usar el modal.</p>
                <div class="w-full">
                    <ItemFormContent
                        :is-open="true"
                        :dialog-type="dialogType"
                        :form="form"
                        :is-full-page="true"
                        :show-advanced-link="false"
                        :show-advanced-sections="true"
                        :show-internal-loading-overlay="false"
                        @close="handleClose"
                        @saved="handleSaved"
                        @refresh="handleRefresh"
                        @error="handleError"
                        @loading-state="handleLoadingState"
                    />
                </div>
            </div>
        </section>
    </AppLayout>
</template>
