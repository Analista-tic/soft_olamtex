<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ItemFormContent from './ItemFormContent.vue';
import type { Item } from '@/domain/MasterData';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ITEM_FORM_DRAFT_KEY } from '@/lib/storageKeys';

const breadcrumbs = [
  { title: 'Catalogo de item', href: route('listado') },
  { title: 'Formulario avanzado', href: '' },
];

const props = defineProps<{
  initialForm?: Partial<Item>;
}>();

const form = ref<Partial<Item>>(props.initialForm ?? {});
const isLoading = ref(false);
const showFullscreenLoader = ref(false);
const previousBodyOverflow = ref<string | null>(null);

const OVERLAY_DELAY_MS = 150;
let overlayDelayTimer: ReturnType<typeof setTimeout> | null = null;

const clearOverlayDelay = () => {
  if (overlayDelayTimer) {
    clearTimeout(overlayDelayTimer);
    overlayDelayTimer = null;
  }
};

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

  if (!value) {
    clearOverlayDelay();
    showFullscreenLoader.value = false;
    return;
  }

  clearOverlayDelay();
  overlayDelayTimer = setTimeout(() => {
    overlayDelayTimer = null;
    if (isLoading.value) {
      showFullscreenLoader.value = true;
    }
  }, OVERLAY_DELAY_MS);
}

watch(showFullscreenLoader, (value) => {
  if (typeof document === 'undefined') {
    return;
  }

  if (value) {
    if (previousBodyOverflow.value === null) {
      previousBodyOverflow.value = document.body.style.overflow;
    }
    document.body.style.overflow = 'hidden';
    return;
  }

  if (previousBodyOverflow.value !== null) {
    document.body.style.overflow = previousBodyOverflow.value;
    previousBodyOverflow.value = null;
  }
});

onBeforeUnmount(() => {
  clearOverlayDelay();
  if (typeof document !== 'undefined') {
    document.body.style.overflow = previousBodyOverflow.value ?? '';
  }
  previousBodyOverflow.value = null;
});
</script>

<template>
  <Head title="Formulario avanzado de items" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <transition name="fade">
      <div
        v-if="showFullscreenLoader"
        class="fixed inset-0 z-[2000] grid place-items-center bg-background/60 backdrop-blur-sm"
        role="status"
        aria-live="polite"
      >
        <div class="rounded-xl border border-border bg-card px-6 py-5 shadow-xl">
          <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary"></span>
            <p class="text-sm font-semibold text-foreground">
              Cargando formulario avanzado...
            </p>
          </div>
        </div>
      </div>
    </transition>

    <section class="p-2 flex w-full justify-center">
      <div class="w-full max-w-6xl rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border sm:p-8">
        <h1 class="text-lg font-semibold text-card-foreground">Formulario avanzado</h1>
        <p class="mb-4 text-sm text-muted-foreground">
          Aqui puedes completar todos los campos del item sin usar el modal.
        </p>
        <div class="w-full">
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
        </div>
      </div>
    </section>
  </AppLayout>
</template>
