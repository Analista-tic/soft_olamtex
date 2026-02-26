<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    name: string;
    normalized_name: string;
    generated_description: string;
    section_id: number | null;
    location: string | null;
    measure: number | null;
    quantity: number | null;
    description: string | null;
    sam_base: number | null;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    is_active: boolean;
    created_at: string;
    section?: { id: number; name: string; type?: string } | null;
    product_sheet_reference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    productSheetReference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    machine_reference?: { id: number; code: string; name: string } | null;
    machineReference?: { id: number; code: string; name: string } | null;
    created_by?: { id: number; name: string } | null;
};

const props = defineProps<{
    open: boolean;
    operation: OperationRecord | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'edit'): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (v) => {
        localOpen.value = v;
    },
);
watch(localOpen, (v) => {
    if (!v) emit('close');
});
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    Detalle de operación
                    <Badge v-if="operation" :variant="operation.is_active ? 'default' : 'secondary'" class="text-[10px]">
                        {{ operation.is_active ? 'Activa' : 'Inactiva' }}
                    </Badge>
                </DialogTitle>
                <DialogDescription v-if="operation">
                    {{ operation.name }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="operation" class="space-y-4">
                <!-- Nombre principal -->
                <div class="rounded-md border border-primary/20 bg-primary/5 px-4 py-3 text-center">
                    <p class="font-mono text-lg font-bold text-primary">{{ operation.normalized_name }}</p>
                    <p v-if="operation.codigo" class="mt-1 text-xs text-muted-foreground">{{ operation.codigo }}</p>
                </div>

                <!-- Grid de campos -->
                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Sección</p>
                        <p class="font-medium">{{ operation.section?.name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Tipo</p>
                        <p>{{ operation.section?.type ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Acción</p>
                        <p class="font-semibold uppercase">{{ operation.action }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Parte de prenda</p>
                        <p class="font-semibold uppercase">{{ operation.garment_part }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Medida</p>
                        <p class="font-mono">{{ operation.measure != null ? `${operation.measure} cm` : '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Cantidad</p>
                        <p class="font-mono">{{ operation.quantity ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">SAM base</p>
                        <p class="font-mono font-bold text-primary">
                            {{ operation.sam_base != null ? `${operation.sam_base.toFixed(4)} min` : '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Referencia (Sábana de producto)</p>
                        <p>
                            {{
                                (operation.productSheetReference?.sample_reference ??
                                    operation.productSheetReference?.product_reference ??
                                    operation.product_sheet_reference?.sample_reference ??
                                    operation.product_sheet_reference?.product_reference) ||
                                '-'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Máquina de referencia</p>
                        <p>
                            {{
                                (operation.machineReference ?? operation.machine_reference)
                                    ? `${(operation.machineReference ?? operation.machine_reference)!.code} · ${(operation.machineReference ?? operation.machine_reference)!.name}`
                                    : '-'
                            }}
                        </p>
                    </div>

                    <div class="col-span-2">
                        <p class="text-[11px] font-medium text-muted-foreground">Descripción</p>
                        <p class="uppercase text-muted-foreground">{{ (operation.generated_description || '-').toUpperCase() }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Creado por</p>
                        <p class="text-xs">{{ operation.created_by?.name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-muted-foreground">Fecha de creación</p>
                        <p class="text-xs">{{ operation.created_at ? new Date(operation.created_at).toLocaleDateString('es-CO') : '-' }}</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 border-t pt-4">
                <Button variant="outline" size="sm" @click="emit('edit')"> <Pencil class="mr-1 h-4 w-4" /> Editar </Button>
                <Button variant="outline" @click="localOpen = false">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
