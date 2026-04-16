<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useNotifications } from '@/composables/useNotifications';
import { router } from '@inertiajs/vue3';
import { Archive, Bell, CheckCheck, ExternalLink, Inbox, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
} = useNotifications();

const badgeCount = computed(() => {
    if (unreadCount.value <= 0) return null;
    if (unreadCount.value > 99) return '99+';
    return String(unreadCount.value);
});

async function openNotification(notification: { id: string; url?: string | null }) {
    await markAsRead(notification.id);

    isOpen.value = false;

    if (notification.url) {
        router.visit(notification.url);
    }
}

async function archiveNotification(notification: { id: string }, event: Event) {
    event.stopPropagation();

    await archiveStoredNotification(notification.id);
}

async function deleteNotification(notification: { id: string }, event: Event) {
    event.stopPropagation();

    await deleteStoredNotification(notification.id);
}

function openAllNotifications() {
    isOpen.value = false;
    router.visit('/configuracion/notificaciones');
}

function onOpenChange(open: boolean) {
    isOpen.value = open;

    if (open) {
        void fetchNotifications();
    }
}

onMounted(() => {
    void fetchUnreadCount();
});
</script>

<template>
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
                    <Badge v-if="badgeCount" variant="destructive" class="absolute -right-1.5 -top-1.5 h-4 min-w-4 px-1 text-[10px] leading-none">
                        {{ badgeCount }}
                    </Badge>
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent align="end" side="bottom" :side-offset="10" class="z-[60] w-[380px] max-w-[calc(100vw-1rem)] rounded-xl p-0">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="text-sm font-semibold">Notificaciones</p>
                <Button v-if="unreadCount > 0" variant="ghost" size="sm" class="h-7 text-xs" @click="markAllAsRead">
                    <CheckCheck class="mr-1 h-3.5 w-3.5" />
                    Marcar todas leidas
                </Button>
            </div>

            <div class="max-h-[400px] overflow-y-auto">
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
                        class="group relative flex w-full items-start gap-2 border-b px-4 py-3 text-left transition-colors hover:bg-muted/40"
                        :class="{ 'bg-primary/5': !notification.is_read }"
                        @click="openNotification(notification)"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" :class="{ 'text-primary': !notification.is_read }">
                                {{ notification.title ?? 'Notificacion' }}
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                {{ notification.body }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ formatNotificationDate(notification.created_at) }}
                            </p>
                        </div>

                        <ExternalLink v-if="notification.url" class="mt-1 h-3.5 w-3.5 shrink-0 text-muted-foreground" />

                        <div class="absolute right-2 top-2 hidden items-center gap-1 group-hover:flex">
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted"
                                @click="archiveNotification(notification, $event)"
                                aria-label="Archivar"
                            >
                                <Archive class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                @click="deleteNotification(notification, $event)"
                                aria-label="Eliminar"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </button>
                </template>
            </div>

            <div class="border-t px-4 py-2">
                <Button variant="ghost" size="sm" class="w-full text-xs" @click="openAllNotifications"> Ver todas las notificaciones </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
