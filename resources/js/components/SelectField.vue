<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Label } from '@/components/ui/label';
import type { Option } from '@/domain/shared';
import { autoUpdate, computePosition, flip, offset, shift } from '@floating-ui/dom';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import { Check, ChevronDown, Plus, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type LocalModelValue = Option['id'] | Option['id'][] | null;

const normalizeModelValue = (value: Option['id'] | Option['id'][] | undefined | null, multiple: boolean): LocalModelValue => {
    if (multiple) {
        return Array.isArray(value) ? value : [];
    }
    if (Array.isArray(value)) {
        return value[0] ?? null;
    }
    return value ?? null;
};

const isLocalModelArray = (value: LocalModelValue): value is Option['id'][] => Array.isArray(value);

const props = withDefaults(
    defineProps<{
        label?: string;
        options: Option[];
        required?: boolean;
        modelValue: Option['id'] | Option['id'][] | undefined | null;
        multiple?: boolean;
        placeholder?: string;
        disabled?: boolean;
        searchable?: boolean;
        searchPlaceholder?: string;
        showPlaceholderOption?: boolean;
        searchThreshold?: number;
        buttonClass?: string;
        allowCustom?: boolean;
        customOptionLabel?: string;
        tooltipText?: string;
        getOptionLabel?: (option: Option) => string;
        actionLabel?: string;
        actionIcon?: 'plus' | false;
        actionDisabled?: boolean;
        actionClass?: string;
        actionSticky?: boolean;
        clearable?: boolean;
        invalid?: boolean;
        errorMessage?: string;
        loading?: boolean;
    }>(),
    {
        required: false,
        multiple: false,
        placeholder: '',
        disabled: false,
        searchable: false,
        searchPlaceholder: 'Buscar y seleccionar...',
        showPlaceholderOption: true,
        searchThreshold: 5,
        buttonClass: '',
        allowCustom: false,
        customOptionLabel: '',
        tooltipText: '',
        actionIcon: 'plus',
        actionDisabled: false,
        actionClass: '',
        actionSticky: true,
        clearable: false,
        invalid: false,
        errorMessage: '',
        loading: false,
    },
);
defineOptions({ inheritAttrs: false });

const emit = defineEmits<{
    (e: 'update:modelValue', value: Option['id'] | Option['id'][] | undefined | null): void;
    (e: 'search', searchTerm: string): void;
    (e: 'action', searchTerm: string): void;
}>();

const localModelValue = ref<LocalModelValue>(normalizeModelValue(props.modelValue, props.multiple));

watch(
    () => props.modelValue,
    (newVal) => {
        localModelValue.value = normalizeModelValue(newVal, props.multiple);
    },
);

watch(
    () => props.multiple,
    (multiple) => {
        localModelValue.value = normalizeModelValue(props.modelValue, multiple);
    },
);

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});

const searchTerm = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

const isSearchEnabled = computed(() => {
    if (props.searchable) return true;
    if (props.searchThreshold === 0) return true;
    return props.options.length > props.searchThreshold;
});

watch(searchTerm, (newValue) => {
    if (!isSearchEnabled.value) return;
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        emit('search', newValue);
    }, 300);
});

onBeforeUnmount(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    stopFloatingUpdates();
});

const normalizedSearchTerm = computed(() => searchTerm.value.trim());
const hasExactMatch = computed(() => {
    if (!normalizedSearchTerm.value) return false;
    const term = normalizedSearchTerm.value.toLowerCase();
    return props.options.some((option) => option.name.toLowerCase() === term);
});

const customOption = computed<Option | null>(() => {
    if (!props.allowCustom || props.multiple) return null;
    if (!normalizedSearchTerm.value || hasExactMatch.value) return null;
    const label = props.customOptionLabel
        ? props.customOptionLabel.replace('{value}', normalizedSearchTerm.value)
        : `Usar "${normalizedSearchTerm.value}"`;
    return { id: normalizedSearchTerm.value, name: label };
});

const customSelection = computed<Option | null>(() => {
    if (props.multiple) return null;
    const modelValue = localModelValue.value;
    if (modelValue === null || isLocalModelArray(modelValue)) {
        return null;
    }
    const match = props.options.find((option) => option.id == modelValue);
    if (match) return null;
    const label = String(modelValue);
    return label ? { id: modelValue, name: label } : null;
});

const filteredOptions = computed(() => {
    if (!isSearchEnabled.value) {
        return props.options;
    }
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) {
        return props.options;
    }
    return props.options.filter((option) => option.name.toLowerCase().includes(term));
});

const groupedOptions = computed(() => {
    const groups: Array<{ label: string; options: Option[] }> = [];
    const map = new Map<string, number>();
    filteredOptions.value.forEach((option) => {
        const label = option.groupLabel ?? '';
        let index = map.get(label);
        if (index === undefined) {
            index = groups.length;
            map.set(label, index);
            groups.push({ label, options: [] });
        }
        groups[index].options.push(option);
    });
    return groups;
});

const comboboxValue = computed<Option | Option[] | null>(() => {
    if (props.multiple) {
        const modelValue = localModelValue.value;
        if (!isLocalModelArray(modelValue)) {
            return [];
        }
        return props.options.filter((option) => modelValue.some((selectedId) => selectedId == option.id));
    }
    const modelValue = localModelValue.value;
    if (modelValue === null || isLocalModelArray(modelValue)) {
        return null;
    }
    return props.options.find((option) => option.id == modelValue) ?? customSelection.value ?? null;
});

const selectedOptions = computed(() => {
    if (!props.multiple) return [] as Option[];
    return Array.isArray(comboboxValue.value) ? comboboxValue.value : [];
});

function labelOf(option: Option): string {
    return props.getOptionLabel ? props.getOptionLabel(option) : option.name;
}

function removeSelected(optionId: Option['id']) {
    if (!props.multiple) return;
    if (!isLocalModelArray(localModelValue.value)) return;
    localModelValue.value = localModelValue.value.filter((id) => id != optionId);
}

function clearSelection() {
    localModelValue.value = props.multiple ? [] : null;
    searchTerm.value = '';
}

const placeholderText = computed(() => {
    if (props.placeholder) {
        return props.placeholder;
    }
    return props.searchPlaceholder;
});

function displayValue(item: Option | Option[] | null): string {
    if (props.multiple) {
        if (!Array.isArray(item) || item.length === 0) {
            return '';
        }
        return item.map((opt) => labelOf(opt)).join(', ');
    }
    const selected = item as Option | null;
    return selected ? labelOf(selected) : '';
}

const displayValueForInput = (item: unknown): string => displayValue(item as Option | Option[] | null);

function isOptionSelected(option: Option) {
    if (props.multiple) {
        const modelValue = localModelValue.value;
        if (!isLocalModelArray(modelValue)) {
            return false;
        }
        return modelValue.some((selectedId) => selectedId == option.id);
    }
    const modelValue = localModelValue.value;
    if (modelValue === null || isLocalModelArray(modelValue)) {
        return false;
    }
    return modelValue == option.id;
}

function handleComboboxChange(value: Option | Option[] | null) {
    if (props.multiple) {
        const selected = Array.isArray(value) ? value : [];
        localModelValue.value = selected.map((option) => option.id);
    } else {
        const selected = value as Option | null;
        localModelValue.value = selected?.id ?? null;
    }
    searchTerm.value = '';
}

function handleInput(event: Event) {
    if (!isSearchEnabled.value) return;
    const target = event.target as HTMLInputElement;
    searchTerm.value = target.value;
}

const hasLabel = computed(() => Boolean(props.label?.trim()));
const hasAction = computed(() => Boolean(props.actionLabel?.trim()));
const actionButtonClass = computed(() => {
    const base =
        'w-full inline-flex items-center justify-center gap-2 rounded-full border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:border-border disabled:bg-muted/20 disabled:text-muted-foreground';
    const customClass = props.actionClass || '';
    return customClass ? `${base} ${customClass}` : base;
});

function handleActionClick() {
    if (props.disabled || props.actionDisabled) return;
    const currentSearchTerm = searchTerm.value.trim();
    emit('action', currentSearchTerm);
    searchTerm.value = '';
}

const triggerRef = ref<HTMLElement | null>(null);
const floatingRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const floatingStyles = ref<Record<string, string>>({
    position: 'fixed',
    left: '0px',
    top: '0px',
    width: 'auto',
    minWidth: '100px',
    pointerEvents: 'auto',
    zIndex: '9999',
});
let floatingCleanup: ReturnType<typeof autoUpdate> | null = null;

async function updateFloatingPosition() {
    if (!triggerRef.value || !floatingRef.value) return;
    try {
        const rect = triggerRef.value.getBoundingClientRect();
        const { x, y } = await computePosition(triggerRef.value, floatingRef.value, {
            strategy: 'fixed',
            placement: 'bottom-start',
            middleware: [offset(4), flip({ padding: 8 }), shift({ padding: 8 })],
        });
        const width = Math.max(rect.width, 120);
        floatingStyles.value = {
            position: 'fixed',
            left: `${x}px`,
            top: `${y}px`,
            width: `${width}px`,
            minWidth: `${width}px`,
            pointerEvents: 'auto',
            zIndex: '9999',
        };
    } catch (error) {
        console.warn('Error positioning select dropdown', error);
    }
}

function startFloatingUpdates() {
    if (floatingCleanup || !triggerRef.value || !floatingRef.value) return;
    floatingCleanup = autoUpdate(triggerRef.value, floatingRef.value, updateFloatingPosition, {
        ancestorScroll: true,
        ancestorResize: true,
        elementResize: true,
        animationFrame: true,
    });
    void updateFloatingPosition();
}

function stopFloatingUpdates() {
    if (floatingCleanup) {
        floatingCleanup();
        floatingCleanup = null;
    }
}

watch(triggerRef, () => {
    if (triggerRef.value && floatingRef.value) {
        nextTick(startFloatingUpdates);
    }
});

watch(
    () => floatingRef.value,
    (floatingEl) => {
        if (floatingEl && triggerRef.value) {
            nextTick(startFloatingUpdates);
        } else {
            stopFloatingUpdates();
        }
    },
);
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <template v-if="hasLabel">
            <Label class="flex items-center gap-1.5">
                <span>{{ label }}</span>
                <HelpTooltip v-if="tooltipText" :text="tooltipText" />
                <span v-if="required" class="text-destructive">*</span>
            </Label>
        </template>
        <div class="relative w-full">
            <Combobox
                class="w-full"
                :modelValue="comboboxValue"
                :multiple="multiple"
                :disabled="disabled"
                by="id"
                @update:modelValue="handleComboboxChange"
            >
                <template #default="{ open }">
                    <div ref="triggerRef" class="relative">
                        <!-- Selected chips (only for multiple) -->
                        <div
                            v-if="multiple && selectedOptions.length"
                            class="absolute left-3 top-2 z-0 flex max-w-[70%] flex-wrap items-center gap-2"
                        >
                            <template v-for="opt in selectedOptions" :key="String(opt.id)">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-2 py-0.5 text-xs text-foreground/90 shadow-sm"
                                >
                                    <span class="max-w-[10rem] truncate">{{ labelOf(opt) }}</span>
                                    <button
                                        type="button"
                                        @click.stop.prevent="removeSelected(opt.id)"
                                        class="inline-flex items-center justify-center rounded-full p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>
                            </template>
                        </div>

                        <ComboboxInput
                            v-bind="$attrs"
                            ref="inputRef"
                            class="h-10 min-h-[42px] w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60"
                            :class="[
                                !isSearchEnabled ? 'cursor-default' : '',
                                buttonClass,
                                multiple ? 'pl-28' : '',
                                invalid ? 'border-destructive focus-visible:ring-destructive/40' : '',
                            ]"
                            :aria-invalid="invalid ? 'true' : undefined"
                            :data-invalid="invalid ? 'true' : undefined"
                            :placeholder="placeholderText"
                            :display-value="displayValueForInput"
                            :disabled="disabled"
                            @input="handleInput"
                        />
                        <button
                            v-if="
                                clearable &&
                                localModelValue !== null &&
                                localModelValue !== undefined &&
                                localModelValue !== '' &&
                                !(Array.isArray(localModelValue) && localModelValue.length === 0)
                            "
                            type="button"
                            class="pointer-events-auto absolute right-7 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-muted-foreground/70 transition hover:text-destructive"
                            :class="[disabled ? 'pointer-events-none opacity-60' : '']"
                            :disabled="disabled"
                            @click.stop.prevent="clearSelection"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                        <ComboboxButton
                            class="pointer-events-auto absolute right-3 top-1/2 -translate-y-1/2 rounded-full border border-transparent bg-transparent p-0 text-muted-foreground/70 transition hover:text-muted-foreground"
                            :class="[disabled ? 'pointer-events-none opacity-60' : '']"
                            :disabled="disabled"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </ComboboxButton>
                        <Teleport to="body">
                            <div v-if="open" ref="floatingRef" :style="floatingStyles" class="z-50" @pointerdown.stop @click.stop>
                                <ComboboxOptions
                                    class="relative max-h-52 overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-2xl focus:outline-none"
                                >
                                    <div
                                        v-if="hasAction"
                                        :class="[
                                            'relative isolate z-20 border-b border-border bg-popover px-3 py-2 shadow-sm',
                                            actionSticky ? 'sticky top-0' : '',
                                        ]"
                                    >
                                        <button
                                            type="button"
                                            :class="actionButtonClass"
                                            :disabled="disabled || actionDisabled"
                                            @mousedown.prevent
                                            @click.stop.prevent="handleActionClick"
                                        >
                                            <Plus v-if="actionIcon === 'plus'" class="h-4 w-4" />
                                            {{ actionLabel }}
                                        </button>
                                    </div>
                                    <div
                                        v-if="$slots.header"
                                        :class="['border-b border-border/70 bg-popover px-2 py-2', !hasAction ? 'sticky top-0 z-10' : '']"
                                    >
                                        <slot name="header" :searchTerm="normalizedSearchTerm" />
                                    </div>
                                    <ComboboxOption v-if="customOption" :value="customOption">
                                        <template #default="{ active }">
                                            <div
                                                :class="[
                                                    'relative cursor-pointer select-none py-2 pl-3 pr-9 transition-colors',
                                                    active ? 'bg-accent text-accent-foreground' : 'text-popover-foreground',
                                                ]"
                                            >
                                                <span>
                                                    {{ customOption?.name }}
                                                </span>
                                                <span
                                                    v-if="customOption && isOptionSelected(customOption)"
                                                    :class="[
                                                        'absolute inset-y-0 right-0 flex items-center pr-4 transition-colors',
                                                        active ? 'text-accent-foreground' : 'text-primary',
                                                    ]"
                                                >
                                                    <Check class="h-4 w-4" />
                                                </span>
                                            </div>
                                        </template>
                                    </ComboboxOption>
                                    <ComboboxOption
                                        v-if="!multiple && showPlaceholderOption"
                                        :value="null"
                                        disabled
                                        class="relative cursor-default select-none bg-muted/30 py-2 pl-3 pr-9 text-muted-foreground"
                                    >
                                        {{ placeholderText }}
                                    </ComboboxOption>
                                    <template v-if="groupedOptions.length">
                                        <template v-for="(group, groupIndex) in groupedOptions" :key="group.label || groupIndex">
                                            <div
                                                v-if="group.label"
                                                class="rounded-t-md bg-muted px-3 py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground/70"
                                            >
                                                {{ group.label }}
                                            </div>
                                            <ComboboxOption v-for="option in group.options" :key="String(option.id)" :value="option">
                                                <template #default="{ active }">
                                                    <div
                                                        :class="[
                                                            'relative cursor-pointer select-none py-2 pl-3 pr-9 transition-colors',
                                                            active ? 'bg-accent text-accent-foreground' : 'text-popover-foreground',
                                                        ]"
                                                    >
                                                        <span>
                                                            {{ labelOf(option) }}
                                                        </span>
                                                        <span
                                                            v-if="isOptionSelected(option)"
                                                            :class="[
                                                                'absolute inset-y-0 right-0 flex items-center pr-4 transition-colors',
                                                                active ? 'text-accent-foreground' : 'text-primary',
                                                            ]"
                                                        >
                                                            <Check class="h-4 w-4" />
                                                        </span>
                                                    </div>
                                                </template>
                                            </ComboboxOption>
                                        </template>
                                    </template>
                                    <div v-if="loading" class="px-3 py-2 text-sm text-muted-foreground/70">Buscando...</div>
                                    <div v-else-if="filteredOptions.length === 0 && !customOption" class="px-3 py-2 text-sm text-muted-foreground/70">
                                        <slot name="empty" :search-term="normalizedSearchTerm"> No se encontraron opciones. </slot>
                                    </div>
                                    <div v-if="$slots.footer" class="border-t border-border/70 bg-popover px-2 pb-2 pt-2">
                                        <slot name="footer" :searchTerm="normalizedSearchTerm" />
                                    </div>
                                </ComboboxOptions>
                            </div>
                        </Teleport>
                    </div>
                </template>
            </Combobox>
        </div>
        <p v-if="invalid && errorMessage" class="text-xs text-destructive">{{ errorMessage }}</p>
        <input
            v-if="$attrs['data-testselect']"
            type="hidden"
            :data-testselect="$attrs['data-testselect']"
            :value="localModelValue"
            @change="localModelValue = ($event.target as HTMLInputElement).value"
        />
    </div>
</template>
