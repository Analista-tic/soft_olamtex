<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useNotifications } from '@/composables/useNotifications';
import type { NotificationRow } from '@/domain/Settings';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Archive, Bell, CheckCheck, Inbox, PackageCheck, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const isOpen = ref(false);
const {
    notifications: latestNotifications,
    unreadCount,
    loading: isLoading,
    fetchUnreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    archiveNotification: archiveStoredNotification,
    deleteNotification: deleteStoredNotification,
    formatNotificationDate,
    startPolling,
    stopPolling,
    newNotificationsCount,
    clearNewNotifications,
} = useNotifications();

const showNewBanner = ref(false);
const newBannerCount = ref(0);
let bannerTimer: ReturnType<typeof setTimeout> | null = null;

watch(newNotificationsCount, (count) => {
    if (count <= 0) return;
    newBannerCount.value = count;
    showNewBanner.value = true;
    if (bannerTimer) clearTimeout(bannerTimer);
    bannerTimer = setTimeout(() => {
        showNewBanner.value = false;
        clearNewNotifications();
    }, 5000);
});

const badgeCount = computed(() => {
    if (unreadCount.value <= 0) return null;
    if (unreadCount.value > 99) return '99+';
    return String(unreadCount.value);
});

function getNotificationIcon(notification: NotificationRow) {
    const type = notification.type ?? notification.data?.type ?? '';
    if (type === 'batch_control_novelty') return AlertTriangle;
    if (type === 'inventory_request') return PackageCheck;
    return Bell;
}

function getNotificationIconClass(notification: NotificationRow): string {
    const type = notification.type ?? notification.data?.type ?? '';
    if (type === 'batch_control_novelty') return 'text-amber-500';
    if (type === 'inventory_request') return 'text-blue-500';
    return 'text-muted-foreground';
}

async function openNotification(notification: NotificationRow) {
    await markAsRead(notification.id);
    isOpen.value = false;
    if (notification.url) {
        router.visit(notification.url);
    }
}

async function archiveNotification(notification: NotificationRow, event: Event) {
    event.stopPropagation();
    await archiveStoredNotification(notification.id);
}

async function deleteNotification(notification: NotificationRow, event: Event) {
    event.stopPropagation();
    await deleteStoredNotification(notification.id);
}

function openAllNotifications() {
    isOpen.value = false;
    router.visit('/configuracion/notificaciones');
}

function onOpenChange(open: boolean) {
    isOpen.value = open;
    if (open) void fetchNotifications();
}

onMounted(() => {
    void fetchUnreadCount();
    startPolling(30_000);
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <!-- Banner de nuevas notificaciones -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="showNewBanner"
            class="fixed right-4 top-4 z-[200] flex items-center gap-3 rounded-xl border border-primary/20 bg-primary px-4 py-3 text-primary-foreground shadow-lg"
        >
            <Bell class="h-4 w-4 shrink-0" />
            <span class="text-sm font-medium">
                {{ newBannerCount === 1 ? 'Tienes 1 nueva notificación' : `Tienes ${newBannerCount} nuevas notificaciones` }}
            </span>
            <button
                type="button"
                class="ml-1 rounded p-0.5 opacity-70 transition-opacity hover:opacity-100"
                @click="showNewBanner = false; clearNewNotifications();"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </Transition>

    <Popover :open="isOpen" :modal="false" @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="relative inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-transparent bg-transparent text-muted-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                aria-label="Notificaciones"
                title="Notificaciones"
            >
                <span class="relative inline-flex h-[18px] w-[18px] items-center justify-center">
                    <Bell class="h-[18px] w-[18px]" />
                    <Badge
                        v-if="badgeCount"
                        variant="destructive"
                        class="absolute -right-1.5 -top-1.5 h-4 min-w-4 px-1 text-[10px] leading-none"
                    >
                        {{ badgeCount }}
                    </Badge>
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent align="end" side="bottom" :side-offset="10" class="z-[60] w-[420px] max-w-[calc(100vw-1rem)] rounded-xl p-0">
            <!-- Header -->
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold">Notificaciones</p>
                    <Badge v-if="unreadCount > 0" variant="destructive" class="h-5 px-1.5 text-[10px]">
                        {{ unreadCount > 99 ? '99+' : unreadCount }} pendientes
                    </Badge>
                </div>
                <Button v-if="unreadCount > 0" variant="ghost" size="sm" class="h-7 text-xs" @click="markAllAsRead">
                    <CheckCheck class="mr-1 h-3.5 w-3.5" />
                    Marcar todas leídas
                </Button>
            </div>

            <!-- Lista -->
            <div class="max-h-[480px] overflow-y-auto">
                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <div class="h-5 w-5 animate-spin rounded-full border-b-2 border-primary" />
                </div>

                <div v-else-if="latestNotifications.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                    <Inbox class="mb-2 h-9 w-9 opacity-40" />
                    <p class="text-sm">Sin notificaciones</p>
                </div>

                <template v-else>
                    <button
                        v-for="notification in latestNotifications"
                        :key="notification.id"
                        type="button"
                        class="group relative flex w-full items-start gap-3 border-b px-4 py-3 text-left transition-colors hover:bg-muted/40"
                        :class="{ 'bg-primary/5': !notification.is_read }"
                        @click="openNotification(notification)"
                    >
                        <!-- Icono tipo notificación -->
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted">
                            <component :is="getNotificationIcon(notification)" class="h-3.5 w-3.5" :class="getNotificationIconClass(notification)" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <!-- Título -->
                            <p class="truncate text-sm font-semibold leading-tight" :class="{ 'text-primary': !notification.is_read }">
                                {{ notification.title ?? 'Notificación' }}
                            </p>

                            <!-- Detalle específico: novedad de control de lotes -->
                            <template v-if="(notification.type ?? notification.data?.type) === 'batch_control_novelty'">
                                <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px]">
                                    <span class="font-medium text-foreground">Lote: {{ notification.op_code ?? notification.data?.op_code ?? '—' }}</span>
                                    <span class="text-muted-foreground">·</span>
                                    <span class="text-amber-600 dark:text-amber-400">
                                        {{ notification.from_process ?? notification.data?.from_process ?? '?' }}
                                        →
                                        {{ notification.to_process ?? notification.data?.to_process ?? '?' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                    {{ notification.data?.description ?? notification.body }}
                                </p>
                                <p class="mt-1 text-[10px] text-muted-foreground">
                                    Reportado por {{ notification.reporter ?? notification.data?.reporter ?? '—' }}
                                    · {{ formatNotificationDate(notification.created_at) }}
                                </p>
                            </template>

                            <!-- Detalle genérico para otros tipos -->
                            <template v-else>
                                <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                    {{ notification.body }}
                                </p>
                                <p class="mt-1 text-[10px] text-muted-foreground">
                                    {{ formatNotificationDate(notification.created_at) }}
                                </p>
                            </template>
                        </div>

                        <!-- Indicador no leído -->
                        <span v-if="!notification.is_read" class="mt-2 h-2 w-2 shrink-0 rounded-full bg-primary" />

                        <!-- Acciones hover -->
                        <div class="absolute right-2 top-2 hidden items-center gap-1 group-hover:flex">
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted"
                                aria-label="Archivar"
                                @click="archiveNotification(notification, $event)"
                            >
                                <Archive class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                aria-label="Eliminar"
                                @click="deleteNotification(notification, $event)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </button>
                </template>
            </div>

            <!-- Footer -->
            <div class="border-t px-4 py-2">
                <Button variant="ghost" size="sm" class="w-full text-xs" @click="openAllNotifications">
                    Ver todas las notificaciones
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
