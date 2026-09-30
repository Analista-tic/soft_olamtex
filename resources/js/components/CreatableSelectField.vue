<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Option } from '@/domain/shared';
import { autoUpdate, computePosition, flip, offset, shift } from '@floating-ui/dom';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import { Check, ChevronDown, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Copiado desde SelectField.vue porque el flujo de creacion inline necesita
// reemplazar el contenido del dropdown y controlar su cierre sin modificar el componente base.
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
        serverSearch?: boolean;
        creatable?: boolean;
        createLabel?: string;
        createButtonLabel?: string;
        createPlaceholder?: string;
        instanceId?: string;
        onCreate?: (name: string) => Promise<Option>;
        editable?: boolean;
        deletable?: boolean;
        onUpdateOption?: (option: Option, name: string) => Promise<Option>;
        onDeleteOption?: (option: Option) => Promise<void>;
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
        serverSearch: false,
        creatable: false,
        createLabel: '',
        createButtonLabel: '',
        createPlaceholder: 'Nombre',
        editable: false,
        deletable: false,
    },
);
defineOptions({ inheritAttrs: false });

const emit = defineEmits<{
    (e: 'update:modelValue', value: Option['id'] | Option['id'][] | undefined | null): void;
    (e: 'search', searchTerm: string): void;
    (e: 'action', searchTerm: string): void;
    (e: 'created', option: Option): void;
    (e: 'optionUpdated', option: Option): void;
    (e: 'optionDeleted', option: Option): void;
    (e: 'inline-opened', instanceId: string | undefined): void;
}>();

const localModelValue = ref<LocalModelValue>(normalizeModelValue(props.modelValue, props.multiple));
const pendingCreatedOption = ref<Option | null>(null);
const editingOption = ref<Option | null>(null);
const deletingOption = ref<Option | null>(null);

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
    if (!props.multiple && pendingCreatedOption.value && newValue != pendingCreatedOption.value.id) {
        pendingCreatedOption.value = null;
    }
    emit('update:modelValue', newValue);
});

watch(
    [() => props.creatable, () => props.onCreate],
    ([creatable, onCreate]) => {
        if (import.meta.env.DEV && creatable && !onCreate) {
            console.warn('[CreatableSelectField] creatable=true requiere la prop onCreate');
        }
    },
    { immediate: true },
);

const searchTerm = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

const isCreating = ref(false);
const inlineValue = ref('');
const inlineLoading = ref(false);
const inlineError = ref('');

const isSearchEnabled = computed(() => {
    if (props.creatable) return true;
    if (props.searchable) return true;
    if (props.searchThreshold === 0) return true;
    return props.options.length > props.searchThreshold;
});

watch(searchTerm, (newValue) => {
    if (!isSearchEnabled.value || isCreating.value) return;
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
const optionsWithPendingCreated = computed(() => {
    if (!pendingCreatedOption.value) return props.options;
    const exists = props.options.some((option) => option.id == pendingCreatedOption.value?.id);
    return exists ? props.options : [...props.options, pendingCreatedOption.value];
});
const hasExactMatch = computed(() => {
    if (!normalizedSearchTerm.value) return false;
    const term = normalizedSearchTerm.value.toLowerCase();
    return optionsWithPendingCreated.value.some((option) => option.name.toLowerCase() === term);
});

const customOption = computed<Option | null>(() => {
    if (!props.allowCustom || props.multiple) return null;
    if (!normalizedSearchTerm.value || hasExactMatch.value) return null;
    const label = props.customOptionLabel
        ? props.customOptionLabel.replace('{value}', normalizedSearchTerm.value)
        : `Usar "${normalizedSearchTerm.value}"`;
    return { id: normalizedSearchTerm.value, name: label };
});

// Recuerda todas las opciones vistas: si una búsqueda reemplaza `options` y la seleccionada
// desaparece, se sigue mostrando su nombre en vez del ID.
const knownOptions = new Map<string, Option>();
watch(
    () => props.options,
    (options) => options.forEach((option) => knownOptions.set(String(option.id), option)),
    { immediate: true },
);

const customSelection = computed<Option | null>(() => {
    if (props.multiple) return null;
    const modelValue = localModelValue.value;
    if (modelValue === null || isLocalModelArray(modelValue)) {
        return null;
    }
    const match = props.options.find((option) => option.id == modelValue);
    if (match) return null;
    if (pendingCreatedOption.value && pendingCreatedOption.value.id == modelValue) {
        return pendingCreatedOption.value;
    }
    const known = knownOptions.get(String(modelValue));
    if (known) return known;
    // Sin allowCustom un valor sin opción asociada es un ID: mejor vacío que mostrar el número.
    if (!props.allowCustom) return null;
    const label = String(modelValue);
    return label ? { id: modelValue, name: label } : null;
});

const filteredOptions = computed(() => {
    if (!isSearchEnabled.value || props.serverSearch) {
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
        return modelValue
            .map((selectedId) => props.options.find((option) => option.id == selectedId) ?? knownOptions.get(String(selectedId)))
            .filter((option): option is Option => Boolean(option));
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

const createEntityLabel = computed(() => props.createLabel.trim() || 'elemento');
const showCreateAction = computed(() => {
    if (!props.creatable || props.disabled || props.loading || props.multiple) return false;
    if (!props.onCreate) return false;
    if (isCreating.value) return false;
    return Boolean(normalizedSearchTerm.value) && !hasExactMatch.value;
});
const createActionText = computed(() => {
    if (!normalizedSearchTerm.value) return '';
    if (!props.createLabel.trim()) {
        return `+ Crear "${normalizedSearchTerm.value}"`;
    }
    return `+ Crear ${props.createLabel.trim()} "${normalizedSearchTerm.value}"`;
});
const inlinePlaceholderText = computed(() =>
    props.createPlaceholder.trim() && props.createPlaceholder !== 'Nombre' ? props.createPlaceholder : `Nombre del ${createEntityLabel.value}`,
);

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
    pendingCreatedOption.value = null;
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
        if (selected && pendingCreatedOption.value && selected.id == pendingCreatedOption.value.id) {
            pendingCreatedOption.value = selected;
        }
    }
    searchTerm.value = '';
}

function handleInput(event: Event) {
    if (isCreating.value) return;
    if (!isSearchEnabled.value) return;
    const target = event.target as HTMLInputElement;
    searchTerm.value = target.value;
}

// Con allowCustom el texto escrito a mano se confirma al salir del campo (sin exigir elegir "Usar ...").
// Se difiere para no pisar el click sobre una opción del dropdown, que limpia searchTerm al seleccionar.
function commitTypedValue() {
    if (!props.allowCustom || props.multiple || isCreating.value) return;
    setTimeout(() => {
        const typed = normalizedSearchTerm.value;
        if (!typed) return;
        const term = typed.toLowerCase();
        const match = props.options.find((option) => option.name.toLowerCase() === term);
        localModelValue.value = match ? match.id : typed;
        searchTerm.value = '';
    }, 150);
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
const inlineInputRef = ref<HTMLInputElement | null>(null);
const floatingStyles = ref<Record<string, string>>({
    position: 'fixed',
    left: '0px',
    top: '0px',
    width: 'auto',
    minWidth: '100px',
    pointerEvents: 'auto',
    zIndex: '99999',
});
let floatingCleanup: ReturnType<typeof autoUpdate> | null = null;
const teleportTarget = ref<string | HTMLElement>('body');

onMounted(() => {
    const dlg = document.querySelector('[role="dialog"]') as HTMLElement | null;
    teleportTarget.value = dlg ?? 'body';
});

async function updateFloatingPosition() {
    if (!triggerRef.value || !floatingRef.value) return;
    try {
        const rect = triggerRef.value.getBoundingClientRect();
        const { x, y } = await computePosition(triggerRef.value, floatingRef.value, {
            strategy: 'fixed',
            placement: 'bottom-start',
            middleware: [offset(4), flip({ padding: 4 }), shift({ padding: 4 })],
        });
        const width = Math.max(rect.width, 120);
        floatingStyles.value = {
            position: 'fixed',
            left: `${x}px`,
            top: `${y}px`,
            width: `${width}px`,
            minWidth: `${width}px`,
            pointerEvents: 'auto',
            zIndex: '99999',
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

function resetInlineCreateState({ clearSearch = false }: { clearSearch?: boolean } = {}) {
    isCreating.value = false;
    inlineValue.value = '';
    inlineLoading.value = false;
    inlineError.value = '';
    editingOption.value = null;
    deletingOption.value = null;
    if (clearSearch) {
        searchTerm.value = '';
    }
}

function openInlineEdit(option: Option) {
    if (props.disabled || !props.editable || !props.onUpdateOption) return;
    editingOption.value = option;
    deletingOption.value = null;
    inlineValue.value = labelOf(option);
    inlineError.value = '';
    isCreating.value = true;
    emit('inline-opened', props.instanceId);
    setTimeout(() => {
        inlineInputRef.value?.focus();
        inlineInputRef.value?.select();
    }, 80);
}

function openInlineDelete(option: Option) {
    if (props.disabled || !props.deletable || !props.onDeleteOption) return;
    deletingOption.value = option;
    editingOption.value = null;
    inlineError.value = '';
    isCreating.value = true;
    emit('inline-opened', props.instanceId);
}

async function submitInlineDelete() {
    const option = deletingOption.value;
    if (!option || !props.onDeleteOption) return;

    inlineLoading.value = true;
    inlineError.value = '';

    try {
        await props.onDeleteOption(option);

        if (props.multiple) {
            if (isLocalModelArray(localModelValue.value)) {
                localModelValue.value = localModelValue.value.filter((id) => id != option.id);
            }
        } else if (localModelValue.value == option.id) {
            localModelValue.value = null;
        }

        emit('optionDeleted', option);
        resetInlineCreateState({ clearSearch: true });
    } catch (error) {
        inlineError.value = resolveCreateErrorMessage(error);
    } finally {
        inlineLoading.value = false;
    }
}

function openInlineCreate() {
    if (!showCreateAction.value) return;
    inlineValue.value = normalizedSearchTerm.value;
    inlineError.value = '';
    isCreating.value = true;
    emit('inline-opened', props.instanceId);
    setTimeout(() => {
        inlineInputRef.value?.focus();
        if (inlineValue.value) {
            inlineInputRef.value?.select();
        }
    }, 80);
}

function openInlineCreateFromButton() {
    inlineValue.value = normalizedSearchTerm.value;
    inlineError.value = '';
    isCreating.value = true;
    emit('inline-opened', props.instanceId);
    setTimeout(() => {
        inlineInputRef.value?.focus();
        if (inlineValue.value) {
            inlineInputRef.value?.select();
        }
    }, 80);
}

function cancelInlineCreate() {
    if (inlineLoading.value) return;
    resetInlineCreateState({ clearSearch: true });
    setTimeout(() => {
        inputRef.value?.focus();
    }, 50);
}

function resolveCreateErrorMessage(error: unknown) {
    const data = (error as { response?: { data?: { message?: unknown; errors?: Record<string, unknown> } } } | null)?.response?.data;

    // Laravel 422: el detalle real viene en `errors.<campo>[0]`; `message` es genérico.
    const firstFieldError = Object.values(data?.errors ?? {})
        .flat()
        .find((entry): entry is string => typeof entry === 'string' && entry.trim() !== '');
    if (firstFieldError) return firstFieldError.trim();

    const responseMessage = typeof data?.message === 'string' ? data.message : '';
    if (responseMessage.trim()) return responseMessage.trim();

    const message = error instanceof Error ? error.message.trim() : '';
    if (message) return message;

    return `No se pudo crear el ${createEntityLabel.value}.`;
}

async function submitInlineCreate() {
    const value = inlineValue.value.trim();
    if (!value) {
        inlineError.value = `Ingresa el nombre del ${createEntityLabel.value}.`;
        nextTick(() => {
            inlineInputRef.value?.focus();
        });
        return;
    }

    if (editingOption.value) {
        if (!props.onUpdateOption) {
            inlineError.value = `No se pudo editar el ${createEntityLabel.value}.`;
            return;
        }

        inlineLoading.value = true;
        inlineError.value = '';

        try {
            const updatedOption = await props.onUpdateOption(editingOption.value, value);
            if (!props.multiple) {
                localModelValue.value = updatedOption.id;
            }
            emit('optionUpdated', updatedOption);
            resetInlineCreateState({ clearSearch: true });
        } catch (error) {
            inlineError.value = resolveCreateErrorMessage(error);
        } finally {
            inlineLoading.value = false;
        }
        return;
    }

    if (!props.onCreate) {
        inlineError.value = `No se pudo crear el ${createEntityLabel.value}.`;
        return;
    }

    inlineLoading.value = true;
    inlineError.value = '';

    try {
        const createdOption = await props.onCreate(value);
        pendingCreatedOption.value = createdOption;
        localModelValue.value = createdOption.id;
        emit('created', createdOption);
        resetInlineCreateState({ clearSearch: true });
    } catch (error) {
        inlineError.value = resolveCreateErrorMessage(error);
    } finally {
        inlineLoading.value = false;
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

function closeInlineFromParent() {
    if (isCreating.value && !inlineLoading.value) {
        resetInlineCreateState({ clearSearch: true });
    }
}

defineExpose({ closeInlineFromParent });
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
                            class="h-9 min-h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 pr-10 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60"
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
                            :disabled="disabled || isCreating"
                            @input="handleInput"
                            @blur="commitTypedValue"
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
                            :disabled="disabled || isCreating"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </ComboboxButton>
                        <Teleport :to="teleportTarget">
                            <div v-if="open || isCreating" ref="floatingRef" :style="floatingStyles" class="z-[99999]" @pointerdown.stop @click.stop>
                                <ComboboxOptions
                                    v-show="!isCreating"
                                    static
                                    class="relative max-h-52 overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-2xl focus:outline-none"
                                >
                                    <div
                                        v-if="createButtonLabel && creatable && !disabled"
                                        class="relative sticky top-0 isolate z-20 border-b border-border bg-popover px-3 py-1.5 shadow-sm"
                                    >
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                            @mousedown.prevent
                                            @click.stop.prevent="openInlineCreateFromButton"
                                        >
                                            <Plus class="h-3 w-3" />
                                            {{ createButtonLabel }}
                                        </Button>
                                    </div>
                                    <div
                                        v-if="hasAction"
                                        :class="[
                                            'relative isolate z-20 border-b border-border bg-popover px-3 py-1.5 shadow-sm',
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
                                                    'relative cursor-pointer select-none py-1.5 pl-3 pr-9 text-sm transition-colors',
                                                    active ? 'bg-muted text-foreground' : 'text-popover-foreground',
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
                                        class="relative cursor-default select-none bg-muted/30 py-1.5 pl-3 pr-9 text-sm text-muted-foreground"
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
                                                            'relative flex cursor-pointer select-none items-center gap-2 py-1.5 pl-3 pr-2 text-sm transition-colors',
                                                            active ? 'bg-muted text-foreground' : 'text-popover-foreground',
                                                        ]"
                                                    >
                                                        <span class="min-w-0 flex-1 truncate" :class="[(editable || deletable) ? '' : 'pr-7']">
                                                            {{ labelOf(option) }}
                                                        </span>
                                                        <span
                                                            v-if="isOptionSelected(option) && !(editable || deletable)"
                                                            :class="[
                                                                'absolute inset-y-0 right-0 flex items-center pr-4 transition-colors',
                                                                active ? 'text-accent-foreground' : 'text-primary',
                                                            ]"
                                                        >
                                                            <Check class="h-4 w-4" />
                                                        </span>
                                                        <Check
                                                            v-if="isOptionSelected(option) && (editable || deletable)"
                                                            :class="['h-4 w-4 shrink-0', active ? 'text-accent-foreground' : 'text-primary']"
                                                        />
                                                        <div v-if="(editable || deletable) && !disabled" class="flex shrink-0 items-center gap-0.5">
                                                            <button
                                                                v-if="editable"
                                                                type="button"
                                                                title="Editar"
                                                                class="rounded p-1 text-muted-foreground/70 transition hover:bg-background hover:text-foreground"
                                                                @mousedown.prevent
                                                                @click.stop.prevent="openInlineEdit(option)"
                                                            >
                                                                <Pencil class="h-3.5 w-3.5" />
                                                            </button>
                                                            <button
                                                                v-if="deletable"
                                                                type="button"
                                                                title="Eliminar"
                                                                class="rounded p-1 text-muted-foreground/70 transition hover:bg-destructive/10 hover:text-destructive"
                                                                @mousedown.prevent
                                                                @click.stop.prevent="openInlineDelete(option)"
                                                            >
                                                                <Trash2 class="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>
                                            </ComboboxOption>
                                        </template>
                                    </template>
                                    <div v-if="loading" class="px-3 py-2 text-sm text-muted-foreground/70">Buscando...</div>
                                    <div
                                        v-else-if="filteredOptions.length === 0 && !customOption && !showCreateAction"
                                        class="px-3 py-2 text-sm text-muted-foreground/70"
                                    >
                                        <slot name="empty" :search-term="normalizedSearchTerm"> No se encontraron opciones. </slot>
                                    </div>
                                    <div v-if="showCreateAction" class="border-t border-border bg-popover px-2 py-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-8 w-full min-w-0 items-center justify-start rounded-md px-2 py-1 text-left text-sm font-medium leading-tight text-foreground transition hover:bg-accent hover:text-accent-foreground"
                                            @mousedown.prevent
                                            @click.stop.prevent="openInlineCreate"
                                        >
                                            <span class="min-w-0 break-words">{{ createActionText }}</span>
                                        </button>
                                    </div>
                                    <div v-if="$slots.footer" class="border-t border-border/70 bg-popover px-2 pb-2 pt-2">
                                        <slot name="footer" :searchTerm="normalizedSearchTerm" />
                                    </div>
                                </ComboboxOptions>
                                <div
                                    v-if="isCreating"
                                    class="box-border w-full min-w-0 overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-2xl"
                                >
                                    <div class="min-w-0 space-y-2 p-3">
                                        <template v-if="deletingOption">
                                            <p class="break-words text-sm text-foreground">
                                                ¿Eliminar {{ createEntityLabel }} "<strong>{{ labelOf(deletingOption) }}</strong>"?
                                            </p>
                                            <p v-if="inlineError" class="break-words text-xs leading-tight text-destructive">
                                                {{ inlineError }}
                                            </p>
                                            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                                <Button
                                                    type="button"
                                                    variant="outline-primary"
                                                    size="sm"
                                                    class="min-w-0 max-w-full flex-1 px-3 text-xs sm:flex-none"
                                                    :disabled="inlineLoading"
                                                    @mousedown.prevent
                                                    @click.stop.prevent="cancelInlineCreate"
                                                >
                                                    Cancelar
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    class="min-w-0 max-w-full flex-1 px-3 text-xs sm:flex-none"
                                                    :disabled="inlineLoading"
                                                    @mousedown.prevent
                                                    @click.stop.prevent="submitInlineDelete"
                                                >
                                                    {{ inlineLoading ? 'Eliminando...' : 'Eliminar' }}
                                                </Button>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <input
                                                ref="inlineInputRef"
                                                v-model="inlineValue"
                                                type="text"
                                                tabindex="0"
                                                :placeholder="inlinePlaceholderText"
                                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="inlineLoading"
                                                @keydown.enter.prevent="submitInlineCreate"
                                                @keydown.esc.prevent="cancelInlineCreate"
                                            />
                                            <slot name="inline-extra" :loading="inlineLoading" />
                                            <p v-if="inlineError" class="break-words text-xs leading-tight text-destructive">
                                                {{ inlineError }}
                                            </p>
                                            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                                <Button
                                                    type="button"
                                                    variant="outline-primary"
                                                    size="sm"
                                                    class="min-w-0 max-w-full flex-1 px-3 text-xs sm:flex-none"
                                                    :disabled="inlineLoading"
                                                    @mousedown.prevent
                                                    @click.stop.prevent="cancelInlineCreate"
                                                >
                                                    Cancelar
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="default"
                                                    size="sm"
                                                    class="min-w-0 max-w-full flex-1 px-3 text-xs sm:flex-none"
                                                    :disabled="inlineLoading || !inlineValue.trim()"
                                                    @mousedown.prevent
                                                    @click.stop.prevent="submitInlineCreate"
                                                >
                                                    {{ inlineLoading ? (editingOption ? 'Guardando...' : 'Creando...') : editingOption ? 'Guardar' : 'Crear' }}
                                                </Button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
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
