<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import RollDownloadFormDialog from '@/pages/KardexFabric/FabricRollOrders/RollDownloadFormDialog.vue';
import { computed, ref } from 'vue';
import type { FabricRollView } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type BreadcrumbItem = { title: string; href: string };

const props = defineProps<{ roll?: FabricRollView | null }>();
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Kardex de Telas', href: '/kardex-telas' },
  { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
  { title: 'Descarga detallada', href: '' },
];

const toNumber = (value: string | number | null | undefined): number => {
  if (value === null || value === undefined) return 0;
  const n = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
};

const sizeLabels = ['S', 'M', 'L', 'XL'] as const;
const fallbackQuantity = 18;

const baseQuantity = computed(() => {
  const raw = Math.max(0, Math.round(toNumber(props.roll?.quantityValue)));
  return raw || fallbackQuantity;
});

const createCurve = (distribution: number[]) => {
  return sizeLabels.map((size, index) => {
    const portion = distribution[index] ?? 0;
    const value = Math.max(1, Math.round(baseQuantity.value * portion));
    return { size, value };
  });
};

const primaryCurve = computed(() => createCurve([0.25, 0.35, 0.25, 0.15]));
const liquidationCurve = computed(() => createCurve([0.2, 0.35, 0.25, 0.2]));

const colorReport = computed(() => {
  const distributions = [
    { label: props.roll?.color ?? 'Rojo', weights: [0.5, 0.3, 0.15, 0.05] },
    { label: 'Verde', weights: [0.25, 0.25, 0.25, 0.25] },
    { label: 'Azul', weights: [0.15, 0.35, 0.35, 0.15] },
  ];

  const rows = distributions.map(({ label, weights }) => {
    const sizes = sizeLabels.map((_, idx) => {
      const weight = weights[idx] ?? 0;
      return Math.max(0, Math.round(baseQuantity.value * weight));
    });
    const total = sizes.reduce((sum, value) => sum + value, 0);
    return { label, sizes, total };
  });

  const totals = sizeLabels.map((_, idx) => rows.reduce((sum, row) => sum + row.sizes[idx], 0));
  const grandTotal = totals.reduce((sum, value) => sum + value, 0);

  return [...rows, { label: 'Total', sizes: totals, total: grandTotal }];
});

function formatMeters(value?: string | number | null) {
  const n = toNumber(value);
  return `${n.toFixed(2)} m`;
}

const metersToUse = computed(() => {
  const total = toNumber(props.roll?.quantityValue);
  const used = toNumber(props.roll?.exitQuantityValue);
  return formatMeters(Math.max(0, total - used));
});

const isDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

function openDownloadForm() {
  isDialogOpen.value = true;
}

function handleDownloadSubmit(payload: { roll: FabricRollView | null; data: Record<string, unknown> }) {
  const rollLabel = payload.roll?.roll ? ` para el rollo ${payload.roll.roll}` : '';
  successMessage.value = `Descarga registrada${rollLabel}.`;
  errorMessage.value = '';
}

const downloadNotes = [
  'La informacion de los colores sale de la informacion de color de los rollos.',
  'Para el calculo de unidades solamente se debe tener en cuenta las telas principales, ya que en algunos casos los lotes salen en varios tipos de tela.',
];
</script>

<template>
  <Head title="Descarga detallada" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
      <AlertStack
        :success-message="successMessage"
        :error-message="errorMessage"
        success-title="Éxito"
        error-title="Error"
        @close-success="successMessage = ''"
        @close-error="errorMessage = ''"
      />

      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <h1 class="text-lg font-bold">Descarga por rollo</h1>
          <p class="text-sm text-muted-foreground">
            Registra el descargue con la información estructurada para curvas y reportes de colores.
          </p>
        </div>

        <Button size="sm" class="flex items-center gap-1" @click="openDownloadForm">
          Descargar
        </Button>
      </div>

      <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
        <Table class="text-center">
          <TableHeader>
            <TableRow>
              <TableHead>Rollo</TableHead>
              <TableHead>Ubicación</TableHead>
              <TableHead>Tela</TableHead>
              <TableHead>Color</TableHead>
              <TableHead>Código color</TableHead>
              <TableHead>Ancho</TableHead>
              <TableHead>Cantidad real</TableHead>
              <TableHead>Metros a usar</TableHead>
              <TableHead class="w-[140px]">Acciones</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow>
              <TableCell class="font-medium">{{ props.roll?.roll ?? '-' }}</TableCell>
              <TableCell>{{ props.roll?.location ?? '-' }}</TableCell>
              <TableCell>{{ props.roll?.fabric ?? '-' }}</TableCell>
              <TableCell>{{ props.roll?.color ?? 'Color' }}</TableCell>

              <TableCell>
                <div
                  class="mx-auto flex h-6 min-w-[4rem] items-center justify-center rounded-full border border-sidebar-border/70 text-[11px]"
                  :style="{
                    background: props.roll?.colorCode ?? '#E5E7EB',
                    color: '#1F2937',
                  }"
                >
                  {{ props.roll?.colorCode ?? '#E5E7EB' }}
                </div>
              </TableCell>

              <TableCell>{{ props.roll?.width ?? '0 m' }}</TableCell>
              <TableCell>{{ props.roll?.exitQuantity ?? '0 m' }}</TableCell>
              <TableCell>{{ metersToUse }}</TableCell>

              <TableCell class="flex justify-center">
                <Button variant="outline" size="sm" class="w-full md:w-auto" @click="openDownloadForm">
                  Descargar
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <article class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-card-foreground">Curva principal</p>
            <span class="text-xs font-medium text-muted-foreground">Distribución</span>
          </div>

          <div class="mt-4 grid gap-4 text-center text-sm sm:grid-cols-2">
            <div
              v-for="entry in primaryCurve"
              :key="`primary-${entry.size}`"
              class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-sidebar-border/70 bg-white px-4 py-4 shadow-sm transition hover:border-primary hover:shadow-md"
            >
              <span class="text-xs font-medium text-muted-foreground">{{ entry.size }}</span>
              <span class="text-2xl font-semibold text-card-foreground">{{ entry.value }}</span>
              <span class="text-xs text-muted-foreground">unidades</span>
            </div>
          </div>
        </article>

        <article class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-card-foreground">Curva liquidación</p>
            <span class="text-xs font-medium text-muted-foreground">Distribución</span>
          </div>

          <div class="mt-4 grid gap-4 text-center text-sm sm:grid-cols-2">
            <div
              v-for="entry in liquidationCurve"
              :key="`liquid-${entry.size}`"
              class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-sidebar-border/70 bg-white px-4 py-4 shadow-sm transition hover:border-primary hover:shadow-md"
            >
              <span class="text-xs font-medium text-muted-foreground">{{ entry.size }}</span>
              <span class="text-2xl font-semibold text-card-foreground">{{ entry.value }}</span>
              <span class="text-xs text-muted-foreground">unidades</span>
            </div>
          </div>
        </article>
      </div>

      <section class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
        <p class="text-sm font-semibold text-card-foreground">Reporte de unidades reales</p>

        <div class="mt-3 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="text-left">Colores</TableHead>
                <TableHead v-for="label in sizeLabels" :key="`head-${label}`" class="text-center">
                  {{ label }}
                </TableHead>
                <TableHead class="text-center">Total</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow
                v-for="row in colorReport"
                :key="`color-row-${row.label}`"
                :class="row.label === 'Total' ? 'font-semibold text-card-foreground' : ''"
              >
                <TableCell class="text-left">{{ row.label }}</TableCell>
                <TableCell v-for="(value, idx) in row.sizes" :key="`cell-${row.label}-${idx}`" class="text-center">
                  {{ value }}
                </TableCell>
                <TableCell class="text-center">{{ row.total }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <p v-for="note in downloadNotes" :key="note" class="text-xs text-muted-foreground">
            {{ note }}
          </p>
        </div>
      </section>
    </div>

    <RollDownloadFormDialog
      :modelValue="isDialogOpen"
      :roll="props.roll"
      @update:modelValue="(value) => (isDialogOpen = value)"
      @submit="handleDownloadSubmit"
    />
  </AppLayout>
</template>
