<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

const page = usePage();
const props = page.props;
const order = computed(() => props.value.order || props.value.data || {});
const { company, logoUrl } = useCompanyInfo();

function doPrint() {
  window.print();
}
</script>

<template>
  <div class="print-root" style="background:white;padding:12mm;color:#111;font-family:system-ui,Segoe UI,Roboto,Arial">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <p style="font-size:12px;color:#555">{{ company?.name }}</p>
        <p style="font-size:12px;color:#555">NIT: {{ company?.nit }}</p>
      </div>
      <div style="text-align:center">
        <h1 style="margin:0">Orden de Compra</h1>
        <div>{{ order.value.document_number || order.value.id }}</div>
      </div>
      <div>
        <img :src="logoUrl.value" alt="Logo" style="max-height:48px;object-fit:contain" />
      </div>
    </div>

    <hr style="margin:12px 0;border-color:#eee" />

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
      <div>
        <strong>Proveedor</strong>
        <div>{{ order.value.supplier?.full_name || order.value.contact?.full_name || '-' }}</div>
      </div>
      <div>
        <strong>Identificación</strong>
        <div>{{ order.value.supplier?.id_number || order.value.contact?.id_number || '-' }}</div>
      </div>
      <div>
        <strong>Fecha</strong>
        <div>{{ order.value.date || '-' }}</div>
      </div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-top:12px">
      <thead>
        <tr style="background:#f8f8f8">
          <th style="border:1px solid #ddd;padding:6px">#</th>
          <th style="border:1px solid #ddd;padding:6px">Ítem</th>
          <th style="border:1px solid #ddd;padding:6px">Descripción</th>
          <th style="border:1px solid #ddd;padding:6px;text-align:right">Cantidad</th>
          <th style="border:1px solid #ddd;padding:6px;text-align:right">Precio</th>
          <th style="border:1px solid #ddd;padding:6px;text-align:right">IVA</th>
          <th style="border:1px solid #ddd;padding:6px;text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, idx) in order.value.items || []" :key="idx">
          <td style="border:1px solid #ddd;padding:6px">{{ idx + 1 }}</td>
          <td style="border:1px solid #ddd;padding:6px">{{ item.item?.name || '-' }}</td>
          <td style="border:1px solid #ddd;padding:6px">{{ item.description || '-' }}</td>
          <td style="border:1px solid #ddd;padding:6px;text-align:right">{{ item.quantity }}</td>
          <td style="border:1px solid #ddd;padding:6px;text-align:right">{{ Number(item.price).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
          <td style="border:1px solid #ddd;padding:6px;text-align:right">{{ Math.round(item.tax || 0) }}%</td>
          <td style="border:1px solid #ddd;padding:6px;text-align:right">{{ Number(item.total).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
        </tr>
      </tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <div style="width:260px;border:1px solid #ddd;padding:8px;background:#f7f7f7">
        <div style="display:flex;justify-content:space-between"><span>Subtotal</span><span>{{ Number(order.value.subtotal || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span></div>
        <div style="display:flex;justify-content:space-between"><span>IVA</span><span>{{ Number(order.value.iva || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span></div>
        <div style="display:flex;justify-content:space-between;font-weight:bold;margin-top:8px"><span>Total</span><span>{{ Number(order.value.total || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span></div>
      </div>
    </div>

    <div v-if="order.value.description" style="margin-top:12px"><strong>Observaciones</strong><div>{{ order.value.description }}</div></div>

    <div style="margin-top:36px;display:flex;justify-content:space-between">
      <div style="text-align:center"><div style="border-top:1px solid #888;width:160px;margin:0 auto;height:1px"></div><div>Firma Autorizada</div></div>
      <div style="text-align:center"><div style="border-top:1px solid #888;width:160px;margin:0 auto;height:1px"></div><div>Firma Proveedor</div></div>
    </div>

    <div style="position:fixed;right:12px;top:12px">
      <button @click="doPrint">Imprimir</button>
    </div>
  </div>
</template>
