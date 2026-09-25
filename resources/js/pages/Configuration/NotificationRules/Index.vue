<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import type { NotificationRecipientType, NotificationRule, NotificationRuleOptions, NotificationRuleRecipient } from '@/domain/Settings';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell, Lock, Mail, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    events: NotificationRule[];
    options: NotificationRuleOptions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Reglas de notificaciones', href: '/configuracion/reglas-notificaciones' },
];

const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

const rules = ref<NotificationRule[]>(props.events.map((rule) => ({ ...rule })));
const isSaving = ref(false);

// ── Listado ──────────────────────────────────────────────────────────────────
const searchQuery = ref('');
const moduleFilter = ref<string | null>(null);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isModuleFilterActive = computed(() => moduleFilter.value !== null);

const moduleOptions = computed(() => [...new Set(rules.value.map((rule) => rule.module))].map((module) => ({ id: module, name: module })));

const groups = computed(() => {
    const term = searchQuery.value.trim().toLowerCase();
    const visible = rules.value.filter(
        (rule) =>
            (moduleFilter.value === null || rule.module === moduleFilter.value) &&
            (term === '' || `${rule.name} ${rule.description} ${rule.module}`.toLowerCase().includes(term)),
    );
    const byModule = new Map<string, NotificationRule[]>();
    visible.forEach((rule) => byModule.set(rule.module, [...(byModule.get(rule.module) ?? []), rule]));
    return [...byModule.entries()].map(([module, items]) => ({ module, items }));
});

// ── Destinatarios ────────────────────────────────────────────────────────────
const typeLabels: Record<NotificationRecipientType, { label: string; help: string }> = {
    role: { label: 'Rol', help: 'Todas las personas que tengan ese rol.' },
    area: { label: 'Área', help: 'Todas las personas asignadas a esa área.' },
    user: { label: 'Persona', help: 'Una persona en particular.' },
    dynamic: { label: 'Automático', help: 'Quien corresponda según el caso (por ejemplo, el solicitante o quien creó la factura).' },
};

const typeOptions: { id: NotificationRecipientType; name: string }[] = (Object.keys(typeLabels) as NotificationRecipientType[]).map((id) => ({
    id,
    name: typeLabels[id].label,
}));

function conditionText(recipient: NotificationRuleRecipient): string {
    return `solo si tiene el permiso «${recipient.permission_filter}»`;
}

function channelsText(rule: Pick<NotificationRule, 'channel_database' | 'channel_mail'>): string {
    if (rule.channel_database && rule.channel_mail) return 'campana y correo';
    if (rule.channel_database) return 'campana';
    if (rule.channel_mail) return 'correo';
    return 'ningún canal';
}

// ── Edición ──────────────────────────────────────────────────────────────────
type Draft = {
    enabled: boolean;
    channel_database: boolean;
    channel_mail: boolean;
    exclude_actor: boolean;
    recipients: NotificationRuleRecipient[];
};

const isDialogOpen = ref(false);
const editingKey = ref<string | null>(null);
const draft = ref<Draft>({ enabled: true, channel_database: true, channel_mail: false, exclude_actor: true, recipients: [] });
const dialogError = ref('');
const newType = ref<NotificationRecipientType>('role');
const newValue = ref<string | null>(null);
const restrictByPermission = ref(false);
const newPermission = ref<string | null>(null);

const editingRule = computed(() => rules.value.find((rule) => rule.key === editingKey.value) ?? null);

const valueOptions = computed(() => {
    switch (newType.value) {
        case 'role':
            return props.options.roles;
        case 'area':
            return props.options.areas;
        case 'user':
            return props.options.users;
        default:
            return editingRule.value?.dynamic_options ?? [];
    }
});

const permissionToAdd = computed(() => (restrictByPermission.value ? newPermission.value || null : null));

const canAddRecipient = computed(
    () =>
        newValue.value !== null &&
        newValue.value !== '' &&
        (!restrictByPermission.value || permissionToAdd.value !== null) &&
        !draft.value.recipients.some(
            (r) => r.type === newType.value && r.value === newValue.value && (r.permission_filter ?? null) === permissionToAdd.value,
        ),
);

const isDraftValid = computed(
    () => !draft.value.enabled || (draft.value.recipients.length > 0 && (draft.value.channel_database || draft.value.channel_mail)),
);

const draftHint = computed(() => {
    if (!draft.value.enabled) return 'El aviso está apagado: no se enviará a nadie.';
    if (!draft.value.channel_database && !draft.value.channel_mail) return 'Elige al menos un canal (campana o correo).';
    if (!draft.value.recipients.length) return 'Agrega al menos un destinatario.';
    return `Se enviará por ${channelsText(draft.value)} a ${draft.value.recipients.length} destinatario(s).`;
});

function openEdit(rule: NotificationRule) {
    editingKey.value = rule.key;
    draft.value = {
        enabled: rule.enabled,
        channel_database: rule.channel_database,
        channel_mail: rule.channel_mail,
        exclude_actor: rule.exclude_actor,
        recipients: rule.recipients.map((r) => ({ ...r })),
    };
    dialogError.value = '';
    newType.value = 'role';
    newValue.value = null;
    restrictByPermission.value = false;
    newPermission.value = null;
    isDialogOpen.value = true;
}

function changeType(type: NotificationRecipientType) {
    newType.value = type;
    newValue.value = null;
}

function addRecipient() {
    if (!canAddRecipient.value || newValue.value === null) return;
    const option = valueOptions.value.find((o) => o.id === newValue.value);
    draft.value.recipients.push({
        type: newType.value,
        value: newValue.value,
        label: option?.name ?? newValue.value,
        permission_filter: permissionToAdd.value,
    });
    newValue.value = null;
    restrictByPermission.value = false;
    newPermission.value = null;
}

function removeRecipient(index: number) {
    draft.value.recipients.splice(index, 1);
}

function errorMessageFrom(error: unknown): string {
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data;
    const first = response?.errors ? Object.values(response.errors).flat()[0] : undefined;
    return first || response?.message || 'No se pudo guardar la regla de notificación.';
}

async function persist(rule: NotificationRule, payload: Draft): Promise<boolean> {
    isSaving.value = true;
    try {
        const { data } = await axios.put(`/configuracion/reglas-notificaciones/${rule.key}`, {
            enabled: payload.enabled,
            channel_database: payload.channel_database,
            channel_mail: payload.channel_mail,
            exclude_actor: payload.exclude_actor,
            recipients: payload.recipients.map((r) => ({ type: r.type, value: r.value, permission_filter: r.permission_filter })),
        });
        const index = rules.value.findIndex((item) => item.key === rule.key);
        if (index !== -1 && data?.data) rules.value[index] = data.data as NotificationRule;
        showSuccess(data?.message || 'Regla de notificación guardada correctamente.');
        return true;
    } catch (error) {
        const message = errorMessageFrom(error);
        if (isDialogOpen.value) dialogError.value = message;
        else showError(message);
        return false;
    } finally {
        isSaving.value = false;
    }
}

async function saveDraft() {
    const rule = editingRule.value;
    if (!rule || !isDraftValid.value) return;
    if (await persist(rule, draft.value)) isDialogOpen.value = false;
}

async function toggleEnabled(rule: NotificationRule, enabled: boolean) {
    await persist(rule, {
        enabled,
        channel_database: rule.channel_database,
        channel_mail: rule.channel_mail,
        exclude_actor: rule.exclude_actor,
        recipients: rule.recipients,
    });
}
</script>

<template>
    <Head title="Reglas de notificaciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Reglas de notificaciones</h1>
                <p :class="uiPatterns.typography.pageSubtitle">
                    Cuando algo ocurre en el sistema, aquí decides si se avisa, por dónde y a quién. Usa el botón Editar para cambiarlo.
                </p>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar aviso" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por nombre o descripción"
                            class="h-10 w-full pl-10"
                            aria-label="Buscar aviso"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Módulo" :active="isModuleFilterActive" class="min-w-[180px]">
                    <SelectField v-model="moduleFilter" label="" :options="moduleOptions" placeholder="Todos los módulos" :clearable="true" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Cuando ocurre…</TableHead>
                            <TableHead>Se avisa a</TableHead>
                            <TableHead class="w-[130px]">Por</TableHead>
                            <TableHead class="w-[120px] text-center">Estado</TableHead>
                            <TableHead class="w-[90px] text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="group in groups" :key="group.module">
                            <TableRow class="bg-muted/30 hover:bg-muted/30">
                                <TableCell colspan="5" class="py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ group.module }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="rule in group.items" :key="rule.key">
                                <TableCell class="max-w-[340px] whitespace-normal align-top">
                                    <p class="font-medium">{{ rule.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ rule.description }}</p>
                                </TableCell>
                                <TableCell class="whitespace-normal align-top">
                                    <ul class="space-y-1 text-sm">
                                        <li v-for="(recipient, i) in rule.recipients" :key="i">
                                            {{ recipient.label }}
                                            <span v-if="recipient.type === 'role'" class="text-xs text-muted-foreground">(rol)</span>
                                            <span v-else-if="recipient.type === 'area'" class="text-xs text-muted-foreground">(área)</span>
                                            <span v-if="recipient.permission_filter" class="block text-xs text-muted-foreground">
                                                {{ conditionText(recipient) }}
                                            </span>
                                        </li>
                                        <li v-if="!rule.recipients.length" class="text-xs text-muted-foreground">Nadie todavía</li>
                                    </ul>
                                </TableCell>
                                <TableCell class="align-top text-sm">
                                    <p v-if="rule.channel_database" class="flex items-center gap-1.5">
                                        <Bell class="h-4 w-4 text-muted-foreground" /> Campana
                                    </p>
                                    <p v-if="rule.channel_mail" class="flex items-center gap-1.5">
                                        <Mail class="h-4 w-4 text-muted-foreground" /> Correo
                                    </p>
                                    <p v-if="!rule.channel_database && !rule.channel_mail" class="text-xs text-muted-foreground">Ninguno</p>
                                </TableCell>
                                <TableCell class="align-top">
                                    <div class="flex flex-col items-center gap-1">
                                        <Switch
                                            :model-value="rule.enabled"
                                            :disabled="isSaving"
                                            :aria-label="`${rule.enabled ? 'Apagar' : 'Encender'} el aviso: ${rule.name}`"
                                            @update:model-value="toggleEnabled(rule, Boolean($event))"
                                        />
                                        <span class="text-xs" :class="rule.enabled ? 'text-foreground' : 'text-muted-foreground'">
                                            {{ rule.enabled ? 'Encendido' : 'Apagado' }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="align-top">
                                    <div class="flex justify-center"><EditButton :title="`Editar: ${rule.name}`" @click="openEdit(rule)" /></div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <TableRow v-if="!groups.length">
                            <TableCell colspan="5" class="py-6 text-center text-sm text-muted-foreground">No se encontraron avisos.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <Dialog v-model:open="isDialogOpen">
            <FormDialogContent size="md" class="flex max-h-[95vh] flex-col overflow-hidden p-0">
                <TooltipProvider>
                    <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-6 py-4 dark:border-sidebar-border">
                        <DialogTitle>{{ editingRule?.name }}</DialogTitle>
                        <DialogDescription>{{ editingRule?.description }}</DialogDescription>
                    </DialogHeader>

                    <div class="no-scrollbar min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                        <AlertBox v-if="dialogError" type="error" :message="dialogError" @close="dialogError = ''" />

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <Label for="rule-enabled" class="text-sm font-medium">Enviar este aviso</Label>
                                <p class="text-xs text-muted-foreground">Si lo apagas, el evento sigue ocurriendo pero nadie recibe el aviso.</p>
                            </div>
                            <Switch id="rule-enabled" v-model="draft.enabled" />
                        </div>

                        <fieldset class="space-y-2">
                            <legend class="mb-1 text-sm font-medium">¿Por dónde se envía?</legend>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input v-model="draft.channel_database" type="checkbox" class="h-4 w-4 cursor-pointer accent-primary" />
                                <Bell class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                Campana del sistema <span class="text-xs text-muted-foreground">(dentro de la aplicación)</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input v-model="draft.channel_mail" type="checkbox" class="h-4 w-4 cursor-pointer accent-primary" />
                                <Mail class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                Correo electrónico <span class="text-xs text-muted-foreground">(al email de cada persona)</span>
                            </label>
                        </fieldset>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">¿A quién se avisa?</Label>
                                <HelpTooltip
                                    text="Se avisa a todos los de la lista, sin repetir personas. Con «Automático» el sistema elige a quien corresponda en cada caso, por ejemplo al solicitante."
                                    aria-label="Ayuda sobre a quién se avisa"
                                />
                            </div>

                            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <Table class="text-sm">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Destinatario</TableHead>
                                            <TableHead class="w-[80px] text-center">Quitar</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(recipient, i) in draft.recipients" :key="`${recipient.type}-${recipient.value}-${i}`">
                                            <TableCell class="whitespace-normal">
                                                {{ recipient.label }}
                                                <span class="text-xs text-muted-foreground"
                                                    >({{ typeLabels[recipient.type].label.toLowerCase() }})</span
                                                >
                                                <span v-if="recipient.permission_filter" class="block text-xs text-muted-foreground">
                                                    {{ conditionText(recipient) }}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex justify-center">
                                                    <span
                                                        v-if="recipient.locked"
                                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                                        title="El sistema lo necesita para avisar a quien corresponde; no se puede quitar."
                                                    >
                                                        <Lock class="h-3.5 w-3.5" aria-hidden="true" /> Fijo
                                                    </span>
                                                    <DeleteButton v-else :title="`Quitar a ${recipient.label}`" @click="removeRecipient(i)" />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-if="!draft.recipients.length">
                                            <TableCell colspan="2" class="py-4 text-center text-xs text-muted-foreground"
                                                >Aún no hay destinatarios.</TableCell
                                            >
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <div class="space-y-3 rounded-xl border border-dashed p-4">
                                <div class="grid items-end gap-3 md:grid-cols-[150px_1fr_auto]">
                                    <SelectField
                                        :model-value="newType"
                                        label="Agregar"
                                        :options="typeOptions"
                                        :tooltip-text="typeLabels[newType].help"
                                        @update:model-value="changeType($event as NotificationRecipientType)"
                                    />
                                    <SelectField
                                        v-model="newValue"
                                        :label="typeLabels[newType].label"
                                        :options="valueOptions"
                                        :searchable="newType === 'user' || newType === 'area'"
                                        placeholder="Selecciona..."
                                    />
                                    <Button type="button" size="pill" variant="outline" :disabled="!canAddRecipient" @click="addRecipient">
                                        <Plus class="h-4 w-4" /> Agregar
                                    </Button>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input v-model="restrictByPermission" type="checkbox" class="h-4 w-4 cursor-pointer accent-primary" />
                                    Avisar solo si además tiene un permiso
                                </label>
                                <SelectField
                                    v-if="restrictByPermission"
                                    v-model="newPermission"
                                    label="Permiso requerido"
                                    :options="options.permissions"
                                    :searchable="true"
                                    placeholder="Selecciona un permiso..."
                                />
                            </div>
                        </div>

                        <label class="flex cursor-pointer items-start gap-2 text-sm">
                            <input v-model="draft.exclude_actor" type="checkbox" class="mt-0.5 h-4 w-4 cursor-pointer accent-primary" />
                            <span>
                                No avisar a quien realiza la acción
                                <span class="block text-xs text-muted-foreground"
                                    >Recomendado: evita que alguien reciba un aviso de algo que hizo él mismo.</span
                                >
                            </span>
                        </label>

                        <p class="text-xs" :class="isDraftValid ? 'text-muted-foreground' : 'text-amber-600'" role="status">{{ draftHint }}</p>
                    </div>

                    <DialogFooter class="shrink-0 justify-end gap-3 border-t border-border px-6 py-4">
                        <Button variant="outline" size="pill" @click="isDialogOpen = false">Cancelar</Button>
                        <Button size="pill" :disabled="isSaving || !isDraftValid" @click="saveDraft">{{
                            isSaving ? 'Guardando...' : 'Guardar'
                        }}</Button>
                    </DialogFooter>
                </TooltipProvider>
            </FormDialogContent>
        </Dialog>
    </AppLayout>
</template>
