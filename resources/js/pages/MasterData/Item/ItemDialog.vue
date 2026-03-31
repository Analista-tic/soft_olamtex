<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog } from '@/components/ui/dialog';
import type { Item } from '@/domain/MasterData';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ItemFormContent from './ItemFormContent.vue';

type DialogType = 'create' | 'edit' | 'view';
type SaveMode = Exclude<DialogType, 'view'>;

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        dialogType: DialogType;
        form: Partial<Item>;
        showAdvancedLink?: boolean;
        deferContent?: boolean;
    }>(),
    {
        showAdvancedLink: true,
    },
);
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
    (e: 'saved', mode: SaveMode): void;
    (e: 'error', message: string): void;
    (e: 'loading-state', value: boolean): void;
}>();

const dialogOpen = ref(props.isOpen);
const isLoading = ref(false);
const previousBodyOverflow = ref<string | null>(null);

let overlayDelayTimer: ReturnType<typeof setTimeout> | null = null;
const OVERLAY_DELAY_MS = 150;
const allowOverlay = computed(() => props.dialogType === 'edit' || props.dialogType === 'view');
const showOverlay = computed(() => dialogOpen.value && allowOverlay.value && isLoading.value && !props.deferContent);

const clearOverlayDelay = () => {
    if (overlayDelayTimer) {
        clearTimeout(overlayDelayTimer);
        overlayDelayTimer = null;
    }
};

const hideOverlay = () => {
    clearOverlayDelay();
    isLoading.value = false;
};

const setLoadingState = (value: boolean) => {
    if (!allowOverlay.value || !dialogOpen.value) {
        hideOverlay();
        return;
    }

    if (!value) {
        hideOverlay();
        return;
    }

    clearOverlayDelay();
    overlayDelayTimer = setTimeout(() => {
        overlayDelayTimer = null;
        if (allowOverlay.value && dialogOpen.value) {
            isLoading.value = true;
        }
    }, OVERLAY_DELAY_MS);
};

watch(
    () => props.isOpen,
    (value) => {
        dialogOpen.value = value;
        if (!value) {
            hideOverlay();
        }
    },
);

watch(
    () => props.dialogType,
    () => {
        if (props.dialogType === 'create') {
            hideOverlay();
        }
    },
);

watch(showOverlay, (value) => {
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
    hideOverlay();
    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value ?? '';
    }
    previousBodyOverflow.value = null;
});

function handleDialogOpenChange(value: boolean) {
    dialogOpen.value = value;
    if (!value) {
        setLoadingState(false);
        emit('close');
    }
}

function handleFormClose() {
    setLoadingState(false);
    dialogOpen.value = false;
    emit('close');
}

function handleFormRefresh() {
    emit('refresh');
}

function handleFormSaved(mode: SaveMode) {
    emit('saved', mode);
}

function handleFormError(message: string) {
    emit('error', message);
}

function handleFormLoadingState(value: boolean) {
    if (import.meta.env.DEV) {
        console.debug('[ItemDialog] loading-state event', {
            dialogType: props.dialogType,
            value,
            isOpen: dialogOpen.value,
        });
    }

    if (props.dialogType === 'create') {
        hideOverlay();
        emit('loading-state', value);
        return;
    }

    setLoadingState(value);
    emit('loading-state', value);
}
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
        <div v-if="showOverlay" class="fixed inset-0 z-[2000] grid place-items-center bg-background/60 backdrop-blur-sm">
            <div class="rounded-xl border border-border bg-card px-6 py-5 shadow-xl">
                <div class="flex items-center gap-3">
                    <span class="h-6 w-6 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary" />
                    <p class="text-sm font-medium text-foreground">Cargando información...</p>
                </div>
            </div>
        </div>

        <FormDialogContent v-if="!props.deferContent" class="flex max-h-[95vh] max-w-[180vh] flex-col px-2 py-2" :scrollable="false">
            <ItemFormContent
                :is-open="dialogOpen"
                :dialog-type="props.dialogType"
                :form="props.form"
                :show-advanced-link="props.showAdvancedLink"
                @close="handleFormClose"
                @refresh="handleFormRefresh"
                @saved="handleFormSaved"
                @error="handleFormError"
                @loading-state="handleFormLoadingState"
            />
        </FormDialogContent>
    </Dialog>
</template>
