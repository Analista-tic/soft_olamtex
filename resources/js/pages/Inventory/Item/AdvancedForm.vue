<script setup lang="ts">
import type { Item } from '@/domain/inventory';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { ITEM_FORM_DRAFT_KEY } from './constants';
import ItemFormContent from './ItemFormContent.vue';

const breadcrumbs = [
    { title: 'Catálogo de ítem', href: route('listado') },
    { title: 'Formulario avanzado', href: '' },
];

const props = defineProps<{
    initialForm?: Partial<Item>;
}>();

const form = ref<Partial<Item>>(props.initialForm ?? {});
const isLoading = ref(true);

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
    window.location.href = route('listado');
}

function handleSaved() {
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
    <Head title="Formulario avanzado de ítems" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex w-full justify-center p-2">
            <div class="w-full max-w-6xl rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border sm:p-8">
                <h1 class="text-lg font-semibold text-card-foreground">Formulario avanzado</h1>
                <p class="mb-4 text-sm text-muted-foreground">Aquí puedes completar todos los campos del ítem sin usar el modal.</p>
                <div class="relative w-full">
                    <ItemFormContent
                        :is-open="true"
                        :dialog-type="dialogType"
                        :form="form"
                        :show-advanced-link="false"
                        :show-advanced-sections="true"
                        @close="handleClose"
                        @saved="handleSaved"
                        @refresh="handleRefresh"
                        @error="handleError"
                        @loading-state="handleLoadingState"
                    />
                    <transition name="fade">
                        <div
                            v-if="isLoading"
                            class="absolute inset-0 z-50 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/80 px-6 py-8 backdrop-blur dark:bg-slate-900/80"
                            role="status"
                            aria-live="polite"
                        >
                            <span class="inline-flex h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent"></span>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Cargando formulario avanzado...</p>
                        </div>
                    </transition>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
