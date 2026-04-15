<script setup lang="ts">
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Item } from '@/domain/MasterData';
import axios from 'axios';
import type { PurchaseRequestItem } from '@/domain/Expenses';
import { ImagePlus, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{ items: PurchaseRequestItem[]; readonly?: boolean }>();
const emit = defineEmits(['update:items']);

const catalogItems = ref<Item[]>([]);
const localItems = ref<PurchaseRequestItem[]>([]);
const fileInputRefs = ref<Record<string, HTMLInputElement | null>>({});
const persistedImageUrls = ref<Record<string, string | null>>({});
const isSyncingItems = ref(false);
const showLightbox = ref(false);
const lightboxUrl = ref<string | null>(null);
let lightboxKeydownListenerAttached = false;

const currencyDecimals = 2;

function generateItemUid(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return `purchase-request-item-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function getItemUid(item: Partial<PurchaseRequestItem>, index: number): string {
    return typeof item.uid === 'string' && item.uid.trim() ? item.uid.trim() : `legacy-row-${index}`;
}

function roundCurrency(value: number) {
    const factor = Math.pow(10, currencyDecimals);
    return Math.round(value * factor) / factor;
}

function calcTotals(item: PurchaseRequestItem) {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    item.total = roundCurrency(price * quantity);
}

async function fetchItems() {
    const res = await axios.get('/item/data', { params: { per_page: 1000 } });
    catalogItems.value = res.data.data;
}

function normalizeItem(item: Partial<PurchaseRequestItem>): PurchaseRequestItem {
    const uid = typeof item.uid === 'string' && item.uid.trim() ? item.uid.trim() : generateItemUid();
    const imageUrl = typeof item.image_url === 'string' && item.image_url.trim() ? item.image_url : null;

    if (!(uid in persistedImageUrls.value)) {
        persistedImageUrls.value[uid] = imageUrl && !imageUrl.startsWith('blob:') ? imageUrl : null;
    } else if (imageUrl && !imageUrl.startsWith('blob:')) {
        persistedImageUrls.value[uid] = imageUrl;
    }

    const normalized: PurchaseRequestItem = {
        uid,
        item_id: item.item_id ?? null,
        price: Number(item.price) || 0,
        quantity: Number(item.quantity) || 0,
        description: item.description ?? '',
        total: Number(item.total) || 0,
        name: item.name ?? item.item_name ?? '',
        item: item.item,
        item_name: item.item_name,
        total_tax: item.total_tax != null ? Number(item.total_tax) || 0 : undefined,
        image_url: imageUrl,
        image_file: item.image_file ?? null,
        _delete_image: Boolean(item._delete_image),
    };

    const match = catalogItems.value.find((entry) => Number(entry.id) === Number(normalized.item_id));
    if (!normalized.name) {
        if (match) {
            normalized.name = match.name || '';
        }
    }

    calcTotals(normalized);

    return normalized;
}

function itemsSignature(items: PurchaseRequestItem[]) {
    return JSON.stringify(
        items.map((item, index) => ({
            uid: getItemUid(item, index),
            item_id: item.item_id ?? null,
            name: item.name ?? '',
            price: Number(item.price) || 0,
            quantity: Number(item.quantity) || 0,
            description: item.description ?? '',
            total: Number(item.total) || 0,
            total_tax: item.total_tax ?? null,
            image_url: item.image_url ?? null,
            image_file: item.image_file
                ? {
                      name: item.image_file.name,
                      size: item.image_file.size,
                      type: item.image_file.type,
                      lastModified: item.image_file.lastModified,
                  }
                : null,
            _delete_image: Boolean(item._delete_image),
        })),
    );
}

function revokeBlobUrl(url?: string | null) {
    if (typeof url === 'string' && url.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

function cleanupRemovedBlobUrls(previousItems: PurchaseRequestItem[], nextItems: PurchaseRequestItem[]) {
    const nextUrls = new Set(nextItems.map((item) => item.image_url).filter(Boolean));

    previousItems.forEach((item) => {
        if (item.image_url && item.image_url.startsWith('blob:') && !nextUrls.has(item.image_url)) {
            revokeBlobUrl(item.image_url);
        }
    });
}

function registerFileInput(uid: string, element: HTMLInputElement | null) {
    if (element) {
        fileInputRefs.value[uid] = element;
        return;
    }

    delete fileInputRefs.value[uid];
}

function triggerImagePicker(item: PurchaseRequestItem, index: number) {
    if (props.readonly) {
        return;
    }

    fileInputRefs.value[getItemUid(item, index)]?.click();
}

function handleImageSelected(event: Event, item: PurchaseRequestItem, index: number) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    const uid = getItemUid(item, index);
    if (!(uid in persistedImageUrls.value)) {
        persistedImageUrls.value[uid] = item.image_url && !item.image_url.startsWith('blob:') ? item.image_url : null;
    }

    revokeBlobUrl(item.image_url);
    item.image_file = file;
    item.image_url = URL.createObjectURL(file);
    item._delete_image = false;
    input.value = '';
}

function openLightbox(url?: string | null) {
    if (!url) {
        return;
    }

    lightboxUrl.value = url;
    showLightbox.value = true;
}

function closeLightbox() {
    showLightbox.value = false;
    lightboxUrl.value = null;
}

function handleLightboxKeydown(event: KeyboardEvent) {
    if (!showLightbox.value || event.key !== 'Escape') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    closeLightbox();
}

function syncLightboxKeydownListener(isOpen: boolean) {
    if (isOpen && !lightboxKeydownListenerAttached) {
        document.addEventListener('keydown', handleLightboxKeydown, true);
        lightboxKeydownListenerAttached = true;
        return;
    }

    if (!isOpen && lightboxKeydownListenerAttached) {
        document.removeEventListener('keydown', handleLightboxKeydown, true);
        lightboxKeydownListenerAttached = false;
    }
}

function removeItemImage(item: PurchaseRequestItem, index: number) {
    const uid = getItemUid(item, index);
    const hadPersistedImage = Boolean(persistedImageUrls.value[uid]);
    const removedImageUrl = item.image_url;

    revokeBlobUrl(item.image_url);
    item.image_file = null;
    item.image_url = null;
    item._delete_image = hadPersistedImage;

    if (removedImageUrl && lightboxUrl.value === removedImageUrl) {
        closeLightbox();
    }

    const fileInput = fileInputRefs.value[uid];
    if (fileInput) {
        fileInput.value = '';
    }
}

function syncLocalItemsFromProps(items: PurchaseRequestItem[]) {
    const normalizedItems = Array.isArray(items) ? items.map((item) => normalizeItem(item)) : [];

    if (itemsSignature(normalizedItems) === itemsSignature(localItems.value)) {
        return;
    }

    cleanupRemovedBlobUrls(localItems.value, normalizedItems);

    isSyncingItems.value = true;
    localItems.value = normalizedItems;
    isSyncingItems.value = false;
}

function addItem() {
    if (props.readonly) {
        return;
    }

    localItems.value.push({
        uid: generateItemUid(),
        item_id: null,
        price: 0,
        quantity: 1,
        description: '',
        total: 0,
        name: '',
        image_url: null,
        image_file: null,
        _delete_image: false,
    });
}

function removeItem(idx: number) {
    const [removedItem] = localItems.value.splice(idx, 1);
    if (removedItem) {
        if (removedItem.image_url && lightboxUrl.value === removedItem.image_url) {
            closeLightbox();
        }
        revokeBlobUrl(removedItem.image_url);
    }

    emit('update:items', localItems.value);
}

watch(
    () => props.items,
    (items) => {
        syncLocalItemsFromProps(items ?? []);
    },
    { immediate: true, deep: true },
);

watch(
    catalogItems,
    () => {
        syncLocalItemsFromProps(localItems.value);
    },
    { immediate: true },
);

watch(
    localItems,
    (items) => {
        if (isSyncingItems.value) {
            return;
        }

        const normalizedItems = items.map((item) => {
            const normalized = normalizeItem(item);
            const match = catalogItems.value.find((entry) => entry.name?.toLowerCase() === (normalized.name || '').toLowerCase());
            normalized.item_id = match ? Number(match.id) : normalized.name && normalized.name.trim() ? normalized.name.trim() : null;
            calcTotals(normalized);
            return normalized;
        });

        const currentSignature = itemsSignature(items);
        const normalizedSignature = itemsSignature(normalizedItems);

        if (normalizedSignature !== currentSignature) {
            isSyncingItems.value = true;
            localItems.value = normalizedItems;
            isSyncingItems.value = false;
        }

        emit('update:items', normalizedItems);
    },
    { deep: true, immediate: true },
);

watch(showLightbox, (isOpen) => {
    syncLightboxKeydownListener(isOpen);
});

onMounted(() => {
    fetchItems();
});

onBeforeUnmount(() => {
    syncLightboxKeydownListener(false);
    localItems.value.forEach((item) => revokeBlobUrl(item.image_url));
});

defineExpose({ addItem });
</script>

<template>
    <div class="w-full" style="overflow: visible; max-height: none">
        <div class="overflow-x-auto rounded-lg border border-border" style="overflow: visible; max-height: none">
            <Table
                class="min-w-full table-fixed border-separate border-spacing-x-2 border-spacing-y-1 text-center"
                style="overflow: visible; max-height: none"
            >
                <TableHeader>
                    <TableRow class="border-b border-border/60 text-center">
                        <TableHead class="min-w-[150px] px-1 text-left">Item</TableHead>
                        <TableHead class="min-w-[120px] px-1 text-left">Precio estimado</TableHead>
                        <TableHead class="min-w-[110px] px-1 text-left">Cantidad</TableHead>
                        <TableHead class="min-w-[160px] px-1 text-left">Descripcion</TableHead>
                        <TableHead class="w-20 px-1 text-center"><span class="inline-flex items-center justify-center gap-1">Imagen <HelpTooltip text="Imagen de referencia opcional para el ítem. Ayuda al proveedor a identificar exactamente lo que se necesita." /></span></TableHead>
                        <TableHead class="min-w-[90px] px-1 text-left">Total</TableHead>
                        <TableHead class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="item.uid || idx">
                        <TableCell class="min-w-[150px] p-1 text-left">
                            <InputField
                                v-model="item.name"
                                label=""
                                placeholder="Escribir item"
                                list="items-list"
                                class="w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @focus="$event.target.select()"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 120px">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :decimals="2"
                                :min="0"
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 110px">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px">
                            <InputField
                                v-model="item.description"
                                label=""
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                            />
                        </TableCell>
                        <TableCell class="w-20 p-1 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <input
                                    :ref="(el) => registerFileInput(getItemUid(item, idx), el as HTMLInputElement | null)"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageSelected($event, item, idx)"
                                />
                                <button
                                    v-if="!item.image_url && !props.readonly"
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center text-muted-foreground transition hover:text-foreground"
                                    @click="triggerImagePicker(item, idx)"
                                >
                                    <ImagePlus class="h-5 w-5" />
                                </button>
                                <div v-else-if="item.image_url" class="relative h-10 w-10 overflow-visible rounded-md">
                                    <button
                                        type="button"
                                        class="block h-full w-full overflow-hidden rounded-md"
                                        @click.stop="openLightbox(item.image_url)"
                                    >
                                        <img :src="item.image_url" alt="Imagen del item" class="h-full w-full rounded-md object-cover" />
                                    </button>
                                    <div v-if="!props.readonly" class="absolute right-0 top-0 z-10">
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 translate-x-1 -translate-y-1 items-center justify-center rounded-full bg-red-500 text-white shadow-sm transition hover:bg-red-600"
                                            @click.stop="removeItemImage(item, idx)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <span v-else class="inline-flex h-10 w-10 items-center justify-center text-muted-foreground">
                                    <ImagePlus class="h-5 w-5" />
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 90px">
                            {{
                                Number(item.total || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </TableCell>
                        <TableCell class="p-1 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!props.readonly" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell colspan="7" class="text-center text-gray-400">No hay items agregados.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <datalist id="items-list">
            <option v-for="itemOption in catalogItems" :key="itemOption.id" :value="itemOption.name" />
        </datalist>

        <ImagePreviewDialog
            :open="showLightbox"
            :src="lightboxUrl"
            alt="Imagen ampliada del item"
            @update:open="(value) => (value ? (showLightbox = value) : closeLightbox())"
        />
    </div>
</template>
