<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Image as ImageIcon } from 'lucide-vue-next';
import { computed } from 'vue';

type Attachment = { id: string; name: string; url: string };
type StampingData = {
    designNotes?: string | null;
    attachments?: Attachment[];
};
type ColorSlot = { label: string; name: string; hex: string | null };
type BasicInfoItem = { label: string; value: string };

const LEGACY_STAMPING_META_PREFIX = '@stamping_meta:';

const props = defineProps<{
    open: boolean;
    data: StampingData;
    displayName?: string;
    basicInfoItems?: BasicInfoItem[] | null;
    drawingUrl?: string | null;
    colorCodes?: Array<{ name?: string; hex?: string | null }> | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function sanitizeHex(value: unknown): string | null {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const normalized = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(normalized) ? normalized : null;
}

type StampingMeta = { processes: string; observation: string };

function parseStampingMeta(value: string | null | undefined): StampingMeta {
    const raw = String(value ?? '').trim();
    if (!raw) return { processes: '', observation: '' };

    if (raw.startsWith(LEGACY_STAMPING_META_PREFIX)) {
        try {
            const parsed = JSON.parse(raw.slice(LEGACY_STAMPING_META_PREFIX.length)) as { processes?: unknown; observation?: unknown };
            return {
                processes: String(parsed.processes ?? ''),
                observation: String(parsed.observation ?? ''),
            };
        } catch {
            return { processes: '', observation: '' };
        }
    }

    const match = raw.match(/PROCESOS:\s*([\s\S]*?)\s*OBSERVACIÓN:\s*([\s\S]*)/i);
    if (match) {
        return {
            processes: String(match[1] ?? '').trim(),
            observation: String(match[2] ?? '').trim(),
        };
    }

    return { processes: '', observation: raw };
}

const displayName = computed(() => props.displayName?.trim() || 'Estampado');
const meta = computed(() => parseStampingMeta(props.data.designNotes));
const imageAttachment = computed(() => (Array.isArray(props.data.attachments) ? (props.data.attachments[0] ?? null) : null));

const resolvedDrawingUrl = computed(() => {
    const raw = String(props.drawingUrl ?? '').trim();
    if (!raw) return null;
    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;
    if (raw.startsWith('/')) return raw;
    return `/${raw}`;
});

const colorSlots = computed<ColorSlot[]>(() => {
    const list = props.colorCodes ?? [];
    return list
        .filter((item) => String(item.name ?? '').trim() !== '')
        .map((item, index) => {
            const name = String(item.name ?? '').trim();
            const hex = sanitizeHex(item.hex);
            return { label: `Color ${index + 1}`, name, hex };
        });
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[92vh] max-w-[900px] flex-col overflow-hidden px-2 py-2 data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <FormHeader :title="displayName" :description="`Información de ${displayName.toLowerCase()} registrada en ficha técnica.`" />

            <div class="min-h-0 flex-1 overflow-y-auto pr-1 pt-4">
                <!-- Información base -->
                <div v-if="basicInfoItems && basicInfoItems.length > 0" class="mb-5">
                    <p class="mb-2 text-sm font-semibold text-foreground">Información base</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                        <div v-for="item in basicInfoItems" :key="item.label" class="space-y-0.5">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ item.label }}</p>
                            <p class="text-sm font-medium text-foreground">{{ item.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <!-- Columna izquierda: textos -->
                    <div class="space-y-4">
                        <!-- Procesos -->
                        <div>
                            <p class="mb-1.5 text-sm font-semibold text-foreground">Procesos</p>
                            <div
                                v-if="meta.processes"
                                class="min-h-[80px] rounded-md border border-border bg-muted/20 px-3 py-2 text-sm text-foreground whitespace-pre-wrap"
                            >
                                {{ meta.processes }}
                            </div>
                            <div v-else class="flex min-h-[60px] items-center rounded-md border border-dashed border-border px-3 py-2">
                                <p class="text-sm text-muted-foreground">Sin procesos registrados.</p>
                            </div>
                        </div>

                        <!-- Observación -->
                        <div>
                            <p class="mb-1.5 text-sm font-semibold text-foreground">Observación</p>
                            <div
                                v-if="meta.observation"
                                class="min-h-[80px] rounded-md border border-border bg-muted/20 px-3 py-2 text-sm text-foreground whitespace-pre-wrap"
                            >
                                {{ meta.observation }}
                            </div>
                            <div v-else class="flex min-h-[60px] items-center rounded-md border border-dashed border-border px-3 py-2">
                                <p class="text-sm text-muted-foreground">Sin observaciones registradas.</p>
                            </div>
                        </div>

                        <!-- Colores -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-foreground">Colores — códigos</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="slot in colorSlots"
                                    :key="slot.label"
                                    class="flex items-center gap-2 rounded-md border border-border bg-background px-2 py-2"
                                >
                                    <div
                                        class="h-8 w-8 shrink-0 rounded border border-border bg-muted"
                                        :style="slot.hex ? { backgroundColor: slot.hex } : undefined"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-muted-foreground">{{ slot.label }}</p>
                                        <p class="truncate text-sm font-medium text-foreground">{{ slot.name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha: imágenes -->
                    <div class="space-y-4">
                        <!-- Imagen de estampado -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-foreground">Imagen de {{ displayName.toLowerCase() }}</p>
                            <div
                                v-if="imageAttachment"
                                class="overflow-hidden rounded-md border border-border bg-muted/10"
                            >
                                <img
                                    :src="imageAttachment.url"
                                    :alt="`Imagen ${displayName.toLowerCase()}`"
                                    class="h-56 w-full object-contain"
                                />
                            </div>
                            <div v-else class="flex h-40 items-center justify-center rounded-md border border-dashed border-border bg-muted/10">
                                <div class="flex flex-col items-center gap-1.5 text-muted-foreground">
                                    <ImageIcon class="h-8 w-8" />
                                    <p class="text-xs">Sin imagen</p>
                                </div>
                            </div>
                        </div>

                        <!-- Dibujo plano -->
                        <div>
                            <p class="mb-1 text-sm font-semibold text-foreground">Dibujo plano</p>
                            <p class="mb-2 text-xs text-muted-foreground">Referencia visual del producto</p>
                            <div v-if="resolvedDrawingUrl" class="overflow-hidden rounded-md border border-border bg-muted/10">
                                <img :src="resolvedDrawingUrl" alt="Dibujo plano" class="h-44 w-full object-cover" />
                            </div>
                            <div v-else class="flex h-32 items-center justify-center rounded-md border border-dashed border-border bg-muted/10">
                                <p class="text-sm text-muted-foreground">Sin dibujo plano</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 shrink-0 border-t bg-background px-1 pt-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
