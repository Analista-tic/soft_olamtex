<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ItemFormContent from './ItemFormContent.vue';
import type { Item } from '@/domain/inventory';
import { computed, ref, watch, onMounted } from 'vue';
import { ITEM_FORM_DRAFT_KEY } from './constants';

const breadcrumbs = [
  { title: 'Catálogo de ítem', href: route('listado') },
  { title: 'Formulario avanzado', href: '' },
];

const props = defineProps<{
  initialForm?: Partial<Item>;
}>();


const form = ref<Partial<Item>>(props.initialForm ?? {});

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
</script>

<template>
  <Head title="Formulario avanzado de ítems" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <section class="p-2 flex w-full justify-center">
      <div class="w-full max-w-6xl rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border sm:p-8">
        <h1 class="text-lg font-semibold text-card-foreground">Formulario avanzado</h1>
        <p class="mb-4 text-sm text-muted-foreground">
          Aquí puedes completar todos los campos del ítem sin usar el modal.
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
          />
        </div>
      </div>
    </section>
  </AppLayout>
</template>
