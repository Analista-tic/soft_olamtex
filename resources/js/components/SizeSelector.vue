<script setup lang="ts">
import GroupSelect from '@/components/GroupSelect.vue';
import { computed, ref, watch } from 'vue';

interface Props {
    sizeGroups: Record<string, string[]>;
    modelValue: string | undefined;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
const selectedSizes = ref<string[]>([]);

function parseSizeValue(value: string | undefined): Record<string, boolean> {
    if (!value) return {};

    try {
        const parsed = JSON.parse(value);

        if (Array.isArray(parsed)) {
            return Object.fromEntries(parsed.map((size) => [String(size), true]));
        }

        if (parsed && typeof parsed === 'object') {
            return parsed as Record<string, boolean>;
        }
    } catch {
    }

    return {};
}

const sizeOptions = computed(() => {
    return Object.values(props.sizeGroups)
        .flat()
        .map((size) => ({ id: size, name: size }));
});

watch(
    () => props.modelValue,
    (newValue) => {
        const parsed = parseSizeValue(newValue);
        const nextSelected = sizeOptions.value.map((option) => option.id).filter((id) => parsed[id]);
        if (JSON.stringify(selectedSizes.value) !== JSON.stringify(nextSelected)) {
            selectedSizes.value = nextSelected;
        }
    },
    { immediate: true },
);

watch(
    selectedSizes,
    (newValue) => {
        const selectedSet = new Set(newValue);
        const payload = Object.fromEntries(sizeOptions.value.map((option) => [option.id, selectedSet.has(option.id)]));
        const serializedPayload = JSON.stringify(payload);
        if (serializedPayload !== (props.modelValue ?? '')) {
            emit('update:modelValue', serializedPayload);
        }
    },
    { deep: true },
);
</script>

<template>
    <GroupSelect
        v-model="selectedSizes"
        label=""
        :options="sizeOptions"
        placeholder="Selecciona tallas"
        :disabled="props.disabled"
        :searchable="true"
        search-placeholder="Buscar talla"
    />
</template>
