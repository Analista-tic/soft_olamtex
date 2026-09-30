<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useAccentColor } from '@/composables/useAccentColor';
import { printElement } from '@/utils/print';

useAccentColor();

const page = usePage();
const invoice = computed(() => (page.props as Record<string, any>).invoice || {});
const { company } = useCompanyInfo();

const fmtDate = (d: string) => {
    if (!d) return '—';
    try {
        return new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(d));
    } catch { return d; }
};

const fmtCOP = (v: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(v ?? 0));

const items = computed(() => invoice.value.items || []);
const subtotal = computed(() => items.value.reduce((s: number, i: any) => s + Number(i.total ?? 0), 0));
const iva      = computed(() => items.value.reduce((s: number, i: any) => s + Number(i.total_tax ?? 0), 0));
const total    = computed(() => subtotal.value + iva.value);

const generatedAt = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
}).format(new Date());

async function doPrint() {
    await printElement('print-root');
}
</script>

<template>
  <div id="print-root" style="background:white;padding:12mm 14mm;color:#111;font-family:system-ui,Segoe UI,Roboto,Arial;max-width:900px;margin:0 auto">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <!-- Empresa -->
      <div style="min-width:160px">
        <div style="font-size:13px;font-weight:400;color:#111">{{ company?.name || 'Olamtex' }}</div>
        <div style="font-size:11px;color:#666;margin-top:2px">NIT: {{ company?.nit || '—' }}</div>
      </div>

      <!-- Título centrado -->
      <div style="text-align:center;flex:1">
        <div style="font-size:17px;font-weight:700;color:#111">Factura de Compra — Tela</div>
        <div style="font-size:13px;color:#555;margin-top:3px;font-weight:500">{{ invoice.document_number || invoice.id || '—' }}</div>
      </div>

      <!-- Logo + fecha -->
      <div style="min-width:160px;text-align:right">
        <img src="/images/olamtex.png" alt="Logo Olamtex" style="max-height:28px;object-fit:contain;display:block;margin-left:auto;margin-bottom:4px" />
        <div style="font-size:10px;color:#888">Generado: {{ generatedAt }}</div>
      </div>
    </div>

    <!-- Línea divisoria -->
    <div style="height:2px;background:#1e293b;margin:10px 0 14px"></div>

    <!-- DATOS PRINCIPALES -->
    <div style="display:flex;gap:32px;margin-bottom:14px">
      <div>
        <div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.5px">Proveedor</div>
        <div style="font-size:13px;font-weight:600;margin-top:2px">{{ invoice.supplier?.full_name || invoice.contact?.full_name || '—' }}</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.5px">Identificación</div>
        <div style="font-size:13px;font-weight:600;margin-top:2px">{{ invoice.supplier?.id_number || invoice.contact?.id_number || '—' }}</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.5px">Fecha</div>
        <div style="font-size:13px;font-weight:600;margin-top:2px">{{ fmtDate(invoice.date) }}</div>
      </div>
    </div>

    <!-- TABLA DE ÍTEMS -->
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr style="background:#f4f4f5">
          <th style="padding:7px 8px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">#</th>
          <th style="padding:7px 8px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Ítem</th>
          <th style="padding:7px 8px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Descripción</th>
          <th style="padding:7px 8px;text-align:right;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Cantidad</th>
          <th style="padding:7px 8px;text-align:right;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Precio</th>
          <th style="padding:7px 8px;text-align:right;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">IVA</th>
          <th style="padding:7px 8px;text-align:right;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Subtotal</th>
          <th style="padding:7px 8px;text-align:right;font-weight:600;color:#555;border-bottom:2px solid #e4e4e7">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, idx) in items" :key="idx" :style="idx % 2 === 0 ? '' : 'background:#fafafa'">
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;color:#888">{{ (idx as number) + 1 }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;font-weight:500">{{ item.item?.name || '—' }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;color:#555">{{ item.description || '—' }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-variant-numeric:tabular-nums">{{ Number(item.quantity).toLocaleString('es-CO', { maximumFractionDigits: 2 }) }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-variant-numeric:tabular-nums">{{ fmtCOP(item.price) }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;color:#888">{{ Math.round(Number(item.tax ?? 0)) }}%</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-variant-numeric:tabular-nums">{{ fmtCOP(item.total) }}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:600;font-variant-numeric:tabular-nums">{{ fmtCOP(Number(item.total ?? 0) + Number(item.total_tax ?? 0)) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- TOTALES -->
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <div style="width:280px">
        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;font-size:12px">
          <span style="color:#555">Subtotal</span>
          <span style="font-variant-numeric:tabular-nums">{{ fmtCOP(subtotal) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;font-size:12px">
          <span style="color:#555">IVA</span>
          <span style="font-variant-numeric:tabular-nums">{{ fmtCOP(iva) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px;font-weight:700">
          <span>Total</span>
          <span style="font-variant-numeric:tabular-nums">{{ fmtCOP(total) }}</span>
        </div>
      </div>
    </div>

    <!-- Botón imprimir (no se imprime) -->
    <div style="position:fixed;right:16px;top:16px" class="no-print">
      <button
        @click="doPrint"
        style="background:hsl(var(--primary));color:hsl(var(--primary-foreground));border:none;padding:6px 16px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600"
      >Imprimir / PDF</button>
    </div>
  </div>
</template>

<style>
@media print {
  .no-print { display: none !important; }
}
</style>
