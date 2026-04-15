<script lang="ts">
import type { Item as CachedItem } from '@/domain/MasterData';

let cachedCatalogItems: CachedItem[] | null = null;
let catalogItemsPromise: Promise<CachedItem[]> | null = null;
</script>

<script setup lang="ts">
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Item } from '@/domain/MasterData';
import axios from 'axios';
import { ImagePlus, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

type RequestItem = {
    id?: number | null;
    item_id: number | null;
    name: string;
    quantity: number | string;
    image_url?: string | null;
    image_file?: File | null;
    _delete_image?: boolean;
};

const props = defineProps<{ items: RequestItem[]; readonly?: boolean }>();
const emit = defineEmits(['update:items']);

const localItems = ref<RequestItem[]>([]);
const catalogItems = ref<Item[]>([]);
const fileInputRefs = ref<Record<number, HTMLInputElement | null>>({});
const persistedImageUrls = ref<Record<number, string | null>>({});
const isSyncingItems = ref(false);
const lightboxUrl = ref<string | null>(null);
const showLightbox = ref(false);

function itemsSignature(items: RequestItem[]) {
    return JSON.stringify(
        items.map((item) => ({
            id: item.id ?? null,
            item_id: item.item_id ?? null,
            name: item.name ?? '',
            quantity: Number(item.quantity) || 0,
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

async function fetchItems() {
    if (cachedCatalogItems) {
        catalogItems.value = cachedCatalogItems;
        return;
    }
    if (!catalogItemsPromise) {
        catalogItemsPromise = axios
            .get('/item/data', { params: { per_page: 1000 } })
            .then((res) => (res.data.data || []) as Item[])
            .finally(() => {
                catalogItemsPromise = null;
            });
    }
    cachedCatalogItems = await catalogItemsPromise;
    catalogItems.value = cachedCatalogItems;
}

function normalizeItem(item: Partial<RequestItem>, index: number): RequestItem {
    const imageUrl = typeof item.image_url === 'string' && item.image_url.trim() ? item.image_url : null;

    if (imageUrl && !imageUrl.startsWith('blob:')) {
        persistedImageUrls.value[index] = imageUrl;
    } else if (!(index in persistedImageUrls.value)) {
        persistedImageUrls.value[index] = null;
    }

    const normalized: RequestItem = {
        id: item.id ?? null,
        item_id: item.item_id ?? null,
        name: item.name ?? '',
        quantity: Number(item.quantity) || 0,
        image_url: imageUrl,
        image_file: item.image_file ?? null,
        _delete_image: Boolean(item._delete_image),
    };

    const match = catalogItems.value.find((entry) => Number(entry.id) === Number(normalized.item_id));
    if (match) {
        normalized.name = match.name || normalized.name;
    }

    return normalized;
}

function cleanupRemovedBlobUrls(previousItems: RequestItem[], nextItems: RequestItem[]) {
    const nextUrls = new Set(nextItems.map((item) => item.image_url).filter(Boolean));

    previousItems.forEach((item) => {
        if (item.image_url?.startsWith('blob:') && !nextUrls.has(item.image_url)) {
            revokeBlobUrl(item.image_url);
        }
    });
}

function syncLocalItemsFromProps(items: RequestItem[]) {
    const normalizedItems = Array.isArray(items) ? items.map((item, index) => normalizeItem(item, index)) : [];

    if (itemsSignature(normalizedItems) === itemsSignature(localItems.value)) {
        return;
    }

    cleanupRemovedBlobUrls(localItems.value, normalizedItems);

    isSyncingItems.value = true;
    localItems.value = normalizedItems;
    isSyncingItems.value = false;
}

function shiftIndexedStateAfterRemoval(removedIndex: number) {
    const nextPersisted: Record<number, string | null> = {};

    Object.entries(persistedImageUrls.value).forEach(([key, value]) => {
        const numericKey = Number(key);
        if (Number.isNaN(numericKey) || numericKey === removedIndex) {
            return;
        }

        nextPersisted[numericKey > removedIndex ? numericKey - 1 : numericKey] = value;
    });

    persistedImageUrls.value = nextPersisted;
    fileInputRefs.value = {};
}

function addItem() {
    if (props.readonly) return;

    localItems.value.push({
        id: null,
        item_id: null,
        name: '',
        quantity: 1,
        image_url: null,
        image_file: null,
        _delete_image: false,
    });
}

function removeItem(idx: number) {
    if (localItems.value.length <= 1) {
        return;
    }

    const [removedItem] = localItems.value.splice(idx, 1);
    if (removedItem?.image_url) {
        if (lightboxUrl.value === removedItem.image_url) {
            closeLightbox();
        }
        revokeBlobUrl(removedItem.image_url);
    }

    shiftIndexedStateAfterRemoval(idx);
    emit('update:items', localItems.value);
}

function getSelectionValue(item: RequestItem) {
    if (item.item_id !== null && item.item_id !== undefined) {
        return item.item_id;
    }

    return item.name ? item.name : null;
}

function handleItemSelection(item: RequestItem, value: unknown) {
    if (props.readonly) return;

    if (value === null || value === undefined || value === '') {
        item.item_id = null;
        item.name = '';
        emit('update:items', localItems.value);
        return;
    }

    const match = catalogItems.value.find((entry) => String(entry.id) === String(value));
    if (match) {
        item.item_id = Number(match.id);
        item.name = match.name || '';
    } else {
        item.item_id = null;
        item.name = String(value);
    }

    emit('update:items', localItems.value);
}

function triggerImagePicker(index: number) {
    if (props.readonly) {
        return;
    }

    fileInputRefs.value[index]?.click();
}

function handleImageSelected(event: Event, item: RequestItem, index: number) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    if (!(index in persistedImageUrls.value)) {
        persistedImageUrls.value[index] = item.image_url && !item.image_url.startsWith('blob:') ? item.image_url : null;
    }

    revokeBlobUrl(item.image_url);
    item.image_file = file;
    item.image_url = URL.createObjectURL(file);
    item._delete_image = false;
    input.value = '';
}

function removeItemImage(item: RequestItem, index: number) {
    const hadPersistedImage = Boolean(persistedImageUrls.value[index]);
    const removedImageUrl = item.image_url;

    revokeBlobUrl(item.image_url);
    item.image_file = null;
    item.image_url = null;
    item._delete_image = hadPersistedImage;

    if (removedImageUrl && lightboxUrl.value === removedImageUrl) {
        closeLightbox();
    }

    const input = fileInputRefs.value[index];
    if (input) {
        input.value = '';
    }
}

function openLightbox(url: string) {
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
    (val) => {
        if (isSyncingItems.value) {
            return;
        }

        val.forEach((item) => {
            item.quantity = Number(item.quantity) || 0;
            if (item.item_id) {
                const match = catalogItems.value.find((entry) => Number(entry.id) === Number(item.item_id));
                if (match) {
                    item.name = match.name || item.name;
                }
            }
        });

        if (val.length !== props.items.length || val.some((item, idx) => JSON.stringify(item) !== JSON.stringify(props.items[idx]))) {
            emit('update:items', val);
        }
    },
    { deep: true, immediate: true },
);

onMounted(() => {
    void fetchItems();
});

onBeforeUnmount(() => {
    localItems.value.forEach((item) => revokeBlobUrl(item.image_url));
});

defineExpose({ addItem });
</script>

<template>
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-background">
        <div style="overflow: visible; max-height: none">
            <Table class="min-w-full table-fixed text-sm">
                <TableHeader>
                    <TableRow class="border-b border-border bg-muted/50">
                        <TableHead class="min-w-[200px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >Item</TableHead
                        >
                        <TableHead class="min-w-[120px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >Cantidad</TableHead
                        >
                        <TableHead class="w-[90px] px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >Imagen</TableHead
                        >
                        <TableHead class="w-[60px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(item, idx) in localItems"
                        :key="idx"
                        class="border-b border-border/40 transition-colors last:border-0 hover:bg-muted/20"
                    >
                        <TableCell class="min-w-[200px] p-2 text-left">
                            <SelectField
                                :modelValue="getSelectionValue(item)"
                                @update:modelValue="(value) => handleItemSelection(item, value)"
                                :options="catalogItems.map((entry) => ({ id: entry.id, name: entry.name }))"
                                label=""
                                placeholder="Buscar ítem..."
                                :searchable="true"
                                :allowCustom="true"
                                class="w-full"
                                :disabled="props.readonly"
                            />
                        </TableCell>
                        <TableCell class="p-2 text-left" style="width: 120px">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                            />
                        </TableCell>
                        <TableCell class="p-2 text-center align-middle">
                            <div class="flex items-center justify-center">
                                <input
                                    :ref="(el) => (fileInputRefs[idx] = el as HTMLInputElement | null)"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageSelected($event, item, idx)"
                                />
                                <button
                                    v-if="!item.image_url && !item.image_file && !props.readonly"
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center text-muted-foreground transition hover:text-foreground"
                                    @click="triggerImagePicker(idx)"
                                >
                                    <ImagePlus class="h-5 w-5" />
                                </button>
                                <div v-else-if="item.image_url" class="relative h-10 w-10">
                                    <button type="button" class="block h-full w-full overflow-hidden rounded-md" @click.stop="openLightbox(item.image_url)">
                                        <img :src="item.image_url" alt="Imagen del item" class="h-full w-full rounded object-cover" />
                                    </button>
                                    <button
                                        v-if="!props.readonly"
                                        type="button"
                                        class="absolute -right-1 -top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow-sm transition hover:bg-red-600"
                                        @click.stop="removeItemImage(item, idx)"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <span v-else class="inline-flex h-10 w-10 items-center justify-center text-muted-foreground">
                                    <ImagePlus class="h-5 w-5" />
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="p-2 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!props.readonly && localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell colspan="4" class="py-8 text-center text-sm italic text-muted-foreground">No hay items agregados.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <ImagePreviewDialog
            :open="showLightbox"
            :src="lightboxUrl"
            alt="Imagen ampliada del item"
            @update:open="(value) => (value ? (showLightbox = value) : closeLightbox())"
        />
    </div>
</template>
