<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { FabricRollView } from '@/domain/Textil';
import axios from 'axios';
import { Link, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ItemOption = { id: number; name: string; reference: string };

const props = defineProps<{
    modelValue: boolean;
    roll: FabricRollView | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'linked', payload: { rollId: number; itemId: number; itemName: string; message: string }): void;
}>();

const isOpen = ref(props.modelValue);
const searchQuery = ref('');
const items = ref<ItemOption[]>([]);
const selectedItem = ref<ItemOption | null>(null);
const isLoadingItems = ref(false);
const isSaving = ref(false);
const errorMessage = ref('');

watch(() => props.modelValue, (v) => {
    isOpen.value = v;
    if (!v) resetState();
});
watch(isOpen, (v) => {
    emit('update:modelValue', v);
    if (!v) resetState();
});
watch(() => props.roll, (roll) => {
    if (roll) {
        searchQuery.value = '';
        selectedItem.value = null;
        errorMessage.value = '';
        fetchItems('');
    }
});

function resetState() {
    searchQuery.value = '';
    selectedItem.value = null;
    items.value = [];
    errorMessage.value = '';
    isSaving.value = false;
}

function close() { isOpen.value = false; }

let debounce: ReturnType<typeof setTimeout> | null = null;

async function fetchItems(q: string) {
    isLoadingItems.value = true;
    try {
        const { data } = await axios.get('/item/data', {
            params: { search: q, per_page: 30, status: true },
        });
        items.value = (Array.isArray(data?.data) ? data.data : []).map((i: any) => ({
            id: i.id,
            name: i.name,
            reference: i.reference ?? '',
        }));
    } catch {
        items.value = [];
    } finally {
        isLoadingItems.value = false;
    }
}

watch(searchQuery, (q) => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => fetchItems(q), 300);
});

const canSave = computed(() => Boolean(selectedItem.value) && !isSaving.value);

async function save() {
    if (!props.roll || !selectedItem.value) return;

    isSaving.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.patch(
            `/kardex-telas/inventario-rollos/${props.roll.id}/vincular-tela-principal`,
            { item_id: selectedItem.value.id },
        );

        emit('linked', {
            rollId: props.roll.id,
            itemId: selectedItem.value.id,
            itemName: selectedItem.value.name,
            message: data?.message ?? 'Ítem vinculado correctamente.',
        });

        close();
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message ?? 'Error al vincular el ítem. Intenta de nuevo.';
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="flex w-full max-w-lg flex-col">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-center gap-3">
                    <Link class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Vincular ítem al rollo</DialogTitle>
                </div>
                <p class="pb-4 text-sm text-muted-foreground">
                    Rollo <span class="font-semibold text-foreground">{{ roll?.roll }}</span> — {{ roll?.fabric }}
                </p>
            </DialogHeader>

            <div class="space-y-4 px-5 py-4">
                <AlertBox v-if="errorMessage" type="error" :message="errorMessage" @close="errorMessage = ''" />

                <div>
                    <p class="mb-2 text-sm font-medium text-foreground">Buscar ítem</p>
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por nombre o referencia..."
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="h-4 w-4 text-muted-foreground" />
                        </span>
                    </div>
                </div>

                <div v-if="isLoadingItems" class="py-4 text-center text-sm text-muted-foreground">Buscando ítems...</div>

                <ul v-else-if="items.length > 0" class="max-h-56 divide-y divide-border overflow-y-auto rounded-md border border-input">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm transition hover:bg-muted/60"
                        :class="{ 'bg-primary/5 font-medium text-primary': selectedItem?.id === item.id }"
                        @click="selectedItem = item"
                    >
                        <span class="flex-1">{{ item.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ item.reference }}</span>
                    </li>
                </ul>

                <p v-else-if="searchQuery" class="text-sm text-muted-foreground">No se encontraron ítems para "{{ searchQuery }}".</p>

                <div v-if="selectedItem" class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
                    Seleccionado: <span class="font-semibold text-foreground">{{ selectedItem.name }}</span>
                    <span class="ml-2 text-xs text-muted-foreground">({{ selectedItem.reference }})</span>
                </div>
            </div>

            <DialogFooter class="flex flex-col-reverse gap-2 border-t pt-4 md:flex-row md:justify-end">
                <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="close">Cancelar</Button>
                <Button
                    variant="default"
                    type="button"
                    class="mx-auto flex w-full max-w-[160px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    :disabled="!canSave"
                    @click="save"
                >
                    {{ isSaving ? 'Guardando...' : 'Vincular ítem' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
