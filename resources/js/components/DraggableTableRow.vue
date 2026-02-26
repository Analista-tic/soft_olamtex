<script setup lang="ts">
import { GripVertical } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

interface DraggableRowProps {
    index: number;
    isDraggable?: boolean;
}

const props = withDefaults(defineProps<DraggableRowProps>(), {
    isDraggable: true,
});

const emit = defineEmits<{
    (event: 'dragStart', index: number): void;
    (event: 'dragEnd'): void;
    (event: 'dragOver', index: number): void;
    (event: 'drop', index: number): void;
}>();

const isDragging = ref(false);
const autoScrollInterval = ref<number | null>(null);

function handleDragStart(e: DragEvent) {
    if (!props.isDraggable) return;
    isDragging.value = true;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', String(props.index));
    }
    emit('dragStart', props.index);
    startAutoScroll();
}

function handleDragEnd() {
    isDragging.value = false;
    stopAutoScroll();
    emit('dragEnd');
}

// Auto-scroll cuando se arrastra cerca de los bordes
function startAutoScroll() {
    stopAutoScroll();

    autoScrollInterval.value = window.setInterval(() => {
        if (!isDragging.value) return;

        const scrollContainer = findScrollContainer();
        if (!scrollContainer) return;

        const rect = scrollContainer.getBoundingClientRect();
        const mouseY = window.event ? (window.event as MouseEvent).clientY : 0;

        const scrollThreshold = 80; // px desde el borde
        const scrollSpeed = 10; // px por intervalo

        // Scroll hacia arriba
        if (mouseY < rect.top + scrollThreshold) {
            scrollContainer.scrollBy({
                top: -scrollSpeed,
                behavior: 'smooth',
            });
        }
        // Scroll hacia abajo
        else if (mouseY > rect.bottom - scrollThreshold) {
            scrollContainer.scrollBy({
                top: scrollSpeed,
                behavior: 'smooth',
            });
        }
    }, 50);
}

function stopAutoScroll() {
    if (autoScrollInterval.value) {
        clearInterval(autoScrollInterval.value);
        autoScrollInterval.value = null;
    }
}

function findScrollContainer(): HTMLElement | null {
    let element = document.querySelector('tr[draggable="true"]')?.closest('.overflow-y-auto, .overflow-auto, [style*="overflow-y"]');
    if (!element) {
        element = document.querySelector('.DialogContent, [role="dialog"]');
    }
    return element as HTMLElement;
}

function handleDragOver(e: DragEvent) {
    if (!props.isDraggable) return;
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
    emit('dragOver', props.index);
}

function handleDrop(e: DragEvent) {
    if (!props.isDraggable) return;
    e.preventDefault();
    e.stopPropagation();
    emit('drop', props.index);
}

onUnmounted(() => {
    stopAutoScroll();
});
</script>

<template>
    <tr
        :draggable="isDraggable"
        :class="{
            'scale-95 opacity-50': isDragging,
            'cursor-move': isDraggable,
            'border-t hover:bg-muted/30': true,
            'transition-all duration-200 ease-in-out': true,
        }"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @dragover="handleDragOver"
        @drop="handleDrop"
    >
        <td class="px-2 py-2 text-muted-foreground" :class="isDraggable ? 'cursor-move' : 'cursor-default'">
            <GripVertical v-if="isDraggable" class="h-4 w-4 text-muted-foreground" />
        </td>
        <slot />
    </tr>
</template>
