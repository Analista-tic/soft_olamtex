<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { SquareArrowOutUpRight } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DialogDescription from './ui/dialog/DialogDescription.vue';

interface Props {
    sizeGroups: Record<string, string[]>;
    modelValue: string | undefined;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits(['update:modelValue']);
const isDialogOpen = ref(false);
const localModelValue = ref<Record<string, boolean>>({});

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
    } catch (error) {
        console.error('Error al analizar modelValue:', error);
    }

    return {};
}

watch(
    () => props.modelValue,
    (newValue) => {
        localModelValue.value = parseSizeValue(newValue);
    },
    { immediate: true },
);

function updateModelValue(key: string, value: boolean) {
    const updatedValue = { ...localModelValue.value };
    updatedValue[key] = value;
    localModelValue.value = updatedValue;
    emit('update:modelValue', JSON.stringify(localModelValue.value));
}

function toggleGroup(group: string, value: boolean) {
    const updatedValue = { ...localModelValue.value };
    props.sizeGroups[group].forEach((size) => {
        updatedValue[size] = value;
    });
    localModelValue.value = updatedValue;
    emit('update:modelValue', JSON.stringify(localModelValue.value));
}

function isGroupChecked(group: string): boolean {
    return props.sizeGroups[group].every((size) => localModelValue.value[size]);
}

function resetLocalModelValue() {
    localModelValue.value = parseSizeValue(props.modelValue);
}
</script>

<template>
    <div>
        <div class="mb-2">
            <Label class="font-medium">Tallas<span class="text-destructive">*</span></Label>
        </div>
        <Button class="w-full text-start" variant="outline" size="md" @click="isDialogOpen = true"
            >Seleccionar Tallas <SquareArrowOutUpRight
        /></Button>
        <Dialog v-model:open="isDialogOpen" @close="resetLocalModelValue">
            <FormDialogContent class="flex max-h-[95vh] max-w-[100vh] flex-col px-2 py-2">
                <DialogHeader class="flex-shrink-0">
                    <DialogTitle>{{ props.disabled ? 'Ver Tallas' : 'Seleccionar Tallas' }}</DialogTitle>
                    <DialogDescription>
                        {{
                            props.disabled
                                ? 'Tallas disponibles para este producto.'
                                : 'Selecciona las tallas disponibles para este producto. Puedes marcar todas las tallas de un grupo o elegirlas individualmente.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <div v-for="(sizes, group) in sizeGroups" :key="group" class="rounde py-2">
                            <label v-if="!props.disabled" class="flex items-center font-medium text-foreground">
                                <input
                                    type="checkbox"
                                    :checked="isGroupChecked(group)"
                                    class="mr-1 h-3 w-3 rounded border-input bg-background text-primary focus:ring-2 focus:ring-ring"
                                    @change="toggleGroup(group, ($event.target as HTMLInputElement).checked)"
                                />
                                (Seleccionar Todo)
                            </label>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <label
                                    v-for="size in sizes"
                                    :key="size"
                                    class="flex items-center rounded border border-border bg-background px-2 py-1 text-foreground shadow-sm"
                                    :class="props.disabled ? '' : 'cursor-pointer transition-colors hover:bg-accent hover:text-accent-foreground'"
                                    style="flex: 0 0 auto"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="localModelValue[size] ?? false"
                                        class="mr-1 h-3 w-3 rounded border-input bg-background text-primary focus:ring-2 focus:ring-ring"
                                        @change="updateModelValue(size, ($event.target as HTMLInputElement).checked)"
                                        :disabled="props.disabled"
                                    />
                                    {{ size }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-4 flex flex-shrink-0 flex-col gap-2 border-t pt-4 md:flex-row md:justify-end">
                    <div class="w-full md:w-auto">
                        <button
                            type="button"
                            class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-border bg-background px-5 py-2 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            @click="isDialogOpen = false"
                        >
                            Cerrar
                        </button>
                    </div>
                </DialogFooter>
            </FormDialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.summary-active {
    background-color: hsl(var(--muted));
}
</style>
