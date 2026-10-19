<script setup lang="ts">
import AddItemButton from '@/components/common/AddItemButton.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { GripVertical, PlusCircle, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

// 1. DEFINIMOS LOS TIPOS DE DATOS
export interface CuttingPiece {
    id?: number;
    piece_uid: string;
    sequence: number;
    name: string;
    piece_type: 'PRINCIPAL' | 'SECUNDARIO';
    side: 'IZQUIERDA' | 'DERECHA' | 'IZQ/DER' | 'NO_APLICA';
    grain: '90' | '180' | 'SESGO';
    quantity: number;
    notes: string | null;
}

// 2. PROPIEDADES
const props = withDefaults(
    defineProps<{
        modelValue: CuttingPiece[];
        readonly?: boolean;
    }>(),
    {
        modelValue: () => [],
        readonly: false,
    },
);

const emit = defineEmits(['update:modelValue']);

// 3. OPCIONES PARA SELECTORES
// 3. OPCIONES PARA SELECTORES (formato Option[] compatible con SelectField)
const pieceTypeOptions = [
    { id: 'PRINCIPAL', name: 'PRINCIPAL' },
    { id: 'SECUNDARIO', name: 'SECUNDARIO' },
];

const sideOptions = [
    { id: 'IZQUIERDA', name: 'IZQUIERDA' },
    { id: 'DERECHA', name: 'DERECHA' },
    { id: 'IZQ/DER', name: 'IZQ/DER (par: 1 izq + 1 der)' },
    { id: 'NO_APLICA', name: 'NO APLICA' },
];

const grainOptions = [
    { id: '90', name: '90°' },
    { id: '180', name: '180°' },
    { id: 'SESGO', name: 'SESGO' },
];

/** Etiqueta legible para mostrar el lado en modo lectura */
const sideLabel = (side: CuttingPiece['side']) =>
    sideOptions.find((o) => o.id === side)?.name ?? side;

/** Piezas físicas reales: IZQ/DER multiplica por 2 */
const effectivePieces = (piece: CuttingPiece): number =>
    piece.side === 'IZQ/DER' ? piece.quantity * 2 : piece.quantity;

const generateUID = () => (typeof crypto?.randomUUID === 'function' ? crypto.randomUUID() : Date.now().toString(36));

// 4. CLON LOCAL
const localPieces = ref<CuttingPiece[]>([...props.modelValue]);

watch(
    () => props.modelValue,
    (newVal) => {
        localPieces.value = [...newVal];
    },
    { deep: true },
);

// -------------------------------------------------------------------------
// LÓGICA DE DRAG & DROP
// -------------------------------------------------------------------------
const draggedIndex = ref<number | null>(null);
const draggableRowIndex = ref<number | null>(null);

const setDraggable = (index: number | null) => {
    if (props.readonly) return;
    draggableRowIndex.value = index;
};

const onDragStart = (index: number, event: DragEvent) => {
    if (props.readonly) return;
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', index.toString());
    }
};

const onDragEnter = (index: number) => {
    if (props.readonly || draggedIndex.value === null || draggedIndex.value === index) return;

    const items = [...localPieces.value];
    const draggedItem = items[draggedIndex.value];

    items.splice(draggedIndex.value, 1);
    items.splice(index, 0, draggedItem);

    localPieces.value = items;
    draggedIndex.value = index;
};

const onDragEnd = () => {
    draggedIndex.value = null;
    draggableRowIndex.value = null;

    // Al soltar, nos aseguramos de que el campo "sequence" de la base de datos
    // se actualice con el nuevo orden físico que dejó el usuario
    localPieces.value.forEach((p, idx) => {
        p.sequence = idx + 1;
    });

    updateData();
};
// -------------------------------------------------------------------------

// 5. ACCIONES CLÁSICAS
const addPiece = () => {
    localPieces.value.push({
        piece_uid: generateUID(),
        sequence: localPieces.value.length + 1,
        name: '',
        piece_type: 'PRINCIPAL',
        side: 'NO_APLICA',
        grain: '180',
        quantity: 1,
        notes: null,
    });
    updateData();
};

const removePiece = (uid: string) => {
    localPieces.value = localPieces.value.filter((p) => p.piece_uid !== uid);
    localPieces.value.forEach((p, index) => {
        p.sequence = index + 1;
    });
    updateData();
};

const updateData = () => {
    emit('update:modelValue', localPieces.value);
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-foreground">Despiece de Corte</h3>
            <AddItemButton v-if="!readonly" :icon="PlusCircle" label="Agregar pieza" @click="addPiece" />
        </div>

        <div class="overflow-visible rounded-lg border border-border bg-card shadow-sm">
            <Table class="min-w-full">
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-16 px-4 py-1.5 text-center text-sm font-semibold text-muted-foreground">#</TableHead>
                        <TableHead class="min-w-[150px] px-4 py-3 text-left text-sm font-semibold text-muted-foreground"
                            >Nombre de la Pieza</TableHead
                        >
                        <TableHead class="min-w-[140px] px-4 py-1.5 text-left text-sm font-semibold text-muted-foreground">Tela</TableHead>
                        <TableHead class="min-w-[140px] px-4 py-1.5 text-left text-sm font-semibold text-muted-foreground">Lado</TableHead>
                        <TableHead class="min-w-[120px] px-4 py-1.5 text-left text-sm font-semibold text-muted-foreground">Hilo</TableHead>
                        <TableHead class="w-24 px-4 py-3 text-left text-sm font-semibold text-muted-foreground">
                            Cant.
                            <span class="block text-xs font-normal text-muted-foreground/70">por lado</span>
                        </TableHead>
                        <TableHead class="min-w-[200px] px-4 py-1.5 text-left text-sm font-semibold text-muted-foreground">Notas</TableHead>
                        <TableHead v-if="!readonly" class="w-16 px-4 py-1.5 text-center text-sm font-semibold text-muted-foreground"
                            >Acción</TableHead
                        >
                    </TableRow>
                </TableHeader>

                <TransitionGroup name="drag-list" tag="tbody" class="relative [&_tr:last-child]:border-0">
                    <TableRow v-if="localPieces.length === 0" key="empty">
                        <TableCell :colspan="readonly ? 7 : 8" class="px-4 py-8 text-center text-sm italic text-muted-foreground">
                            No hay piezas registradas en el despiece.
                        </TableCell>
                    </TableRow>

                    <TableRow
                        v-for="(piece, index) in localPieces"
                        :key="piece.piece_uid"
                        :draggable="!readonly && draggableRowIndex === index"
                        @dragstart="onDragStart(index, $event)"
                        @dragenter.prevent="onDragEnter(index)"
                        @dragover.prevent
                        @dragend="onDragEnd"
                        class="border-b bg-background transition-colors"
                        :class="[draggedIndex === index ? 'bg-muted opacity-30 shadow-inner' : 'hover:bg-muted/50']"
                    >
                        <TableCell class="px-4 py-1.5 text-center align-middle">
                            <div class="flex items-center justify-center gap-2">
                                <GripVertical
                                    v-if="!readonly"
                                    class="h-4 w-4 cursor-grab text-muted-foreground/40 hover:text-foreground active:cursor-grabbing"
                                    @mousedown="setDraggable(index)"
                                    @mouseup="setDraggable(null)"
                                    @mouseleave="setDraggable(null)"
                                />
                                <span class="w-4 font-medium text-foreground">{{ index + 1 }}</span>
                            </div>
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <span v-if="readonly" class="text-foreground">{{ piece.name }}</span>
                            <Input
                                v-else
                                v-model="piece.name"
                                @update:model-value="updateData"
                                placeholder="Ej. Cuello"
                                class="h-9 w-full bg-background"
                            />
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <span v-if="readonly" class="text-foreground">{{ piece.piece_type }}</span>
                            <SelectField
                                v-else
                                :options="pieceTypeOptions"
                                :model-value="piece.piece_type"
                                placeholder="Tipo"
                                :show-placeholder-option="false"
                                button-class="h-9"
                                @update:model-value="(v) => { piece.piece_type = v as CuttingPiece['piece_type']; updateData(); }"
                            />
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <span v-if="readonly" class="text-foreground">{{ sideLabel(piece.side) }}</span>
                            <SelectField
                                v-else
                                :options="sideOptions"
                                :model-value="piece.side"
                                placeholder="Lado"
                                :show-placeholder-option="false"
                                button-class="h-9"
                                @update:model-value="(v) => { piece.side = v as CuttingPiece['side']; updateData(); }"
                            />
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <span v-if="readonly" class="text-foreground">{{ piece.grain }}</span>
                            <SelectField
                                v-else
                                :options="grainOptions"
                                :model-value="piece.grain"
                                placeholder="Hilo"
                                :show-placeholder-option="false"
                                button-class="h-9"
                                @update:model-value="(v) => { piece.grain = v as CuttingPiece['grain']; updateData(); }"
                            />
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <!-- Modo lectura -->
                            <div v-if="readonly" class="flex flex-col items-center gap-0.5">
                                <span class="text-foreground">{{ piece.quantity }}</span>
                                <span v-if="piece.side === 'IZQ/DER'" class="text-[10px] font-semibold text-primary">
                                    = {{ effectivePieces(piece) }} piezas
                                </span>
                            </div>
                            <!-- Modo edición -->
                            <div v-else class="flex flex-col gap-1">
                                <Input
                                    :model-value="piece.quantity"
                                    @update:model-value="
                                        (v) => {
                                            piece.quantity = Number(v) || 1;
                                            updateData();
                                        }
                                    "
                                    type="number"
                                    min="1"
                                    class="h-9 w-full bg-background text-center"
                                />
                                <span
                                    v-if="piece.side === 'IZQ/DER'"
                                    class="text-center text-[10px] font-semibold leading-none text-primary"
                                >
                                    = {{ effectivePieces(piece) }} piezas
                                    <span class="block font-normal text-muted-foreground">({{ piece.quantity }} izq + {{ piece.quantity }} der)</span>
                                </span>
                            </div>
                        </TableCell>

                        <TableCell class="px-4 py-1.5">
                            <span v-if="readonly" class="text-foreground">{{ piece.notes || '-' }}</span>
                            <Input data-no-uppercase
                                v-else
                                v-model="piece.notes"
                                @update:model-value="updateData"
                                type="text"
                                placeholder="Observaciones..."
                                class="h-8 w-full bg-background"
                            />
                        </TableCell>

                        <TableCell v-if="!readonly" class="w-16 px-4 py-1.5 text-center">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9 rounded-md text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="removePiece(piece.piece_uid)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TransitionGroup>
            </Table>
        </div>
    </div>
</template>

<style scoped>
.drag-list-move,
.drag-list-enter-active,
.drag-list-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drag-list-enter-from,
.drag-list-leave-to {
    opacity: 0;
    transform: scaleY(0.01) translate(30px, 0);
}

.drag-list-leave-active {
    position: absolute;
}
</style>
