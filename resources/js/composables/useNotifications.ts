import axios from 'axios';
import { readonly, ref } from 'vue';

let visibilityListenerActive = false;
let notifFallbackTimer: ReturnType<typeof setInterval> | null = null;
const NOTIF_AWAY_THRESHOLD_MS = 30_000;
const NOTIF_FALLBACK_INTERVAL_MS = 5 * 60_000;
let notifHiddenAt: number | null = null;
const newNotificationsCount = ref(0);

import type { NotificationRow } from '@/domain/Settings/types';

type NotificationId = NotificationRow['id'];

const notifications = ref<NotificationRow[]>([]);
const unreadCount = ref(0);
const loading = ref(false);

function markNotificationAsReadLocally(notificationId: NotificationId): void {
    notifications.value = notifications.value.map((notification) =>
        notification.id === notificationId
            ? {
                  ...notification,
                  is_read: true,
                  read_at: notification.read_at ?? new Date().toISOString(),
              }
            : notification,
    );
}

function rollbackNotifications(previous: NotificationRow[], previousUnreadCount: number): void {
    notifications.value = previous;
    unreadCount.value = previousUnreadCount;
}

export function formatNotificationDate(value: string, locale = 'es-CO'): string {
    const date = new Date(value);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const mins = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);

    if (mins < 1) return 'Ahora';
    if (mins < 60) return `Hace ${mins} min`;
    if (hours < 24) return `Hace ${hours}h`;
    if (days < 7) return `Hace ${days}d`;

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: 'short',
    }).format(date);
}

export function useNotifications() {
    const fetchUnreadCount = async (): Promise<number> => {
        try {
            const response = await axios.get('/configuracion/notificaciones/unread-count');
            const incoming = Number(response.data.count ?? 0);
            if (incoming > unreadCount.value) {
                newNotificationsCount.value = incoming - unreadCount.value;
            }
            unreadCount.value = incoming;
        } catch {
            unreadCount.value = 0;
        }

        return unreadCount.value;
    };

    const fetchNotifications = async (): Promise<NotificationRow[]> => {
        loading.value = true;

        try {
            const response = await axios.get('/configuracion/notificaciones/latest');
            notifications.value = response.data.data ?? [];
        } catch {
            notifications.value = [];
        } finally {
            loading.value = false;
        }

        return notifications.value;
    };

    const markAsRead = async (notificationId: NotificationId): Promise<void> => {
        const notification = notifications.value.find((item) => item.id === notificationId);
        if (!notification || notification.is_read) {
            return;
        }

        try {
            await axios.post(`/configuracion/notificaciones/${notificationId}/read`);
            markNotificationAsReadLocally(notificationId);
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch {
            // no-op
        }
    };

    const markAllAsRead = async (): Promise<void> => {
        try {
            await axios.post('/configuracion/notificaciones/read-all');
            notifications.value = notifications.value.map((notification) => ({
                ...notification,
                is_read: true,
                read_at: notification.read_at ?? new Date().toISOString(),
            }));
            unreadCount.value = 0;
        } catch {
            // no-op
        }
    };

    const archiveNotification = async (notificationId: NotificationId): Promise<void> => {
        const previous = [...notifications.value];
        const previousUnreadCount = unreadCount.value;
        const target = notifications.value.find((notification) => notification.id === notificationId);

        notifications.value = notifications.value.filter((notification) => notification.id !== notificationId);
        if (target && !target.is_read) {
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }

        try {
            await axios.patch(`/configuracion/notificaciones/${notificationId}/archive`);
        } catch {
            rollbackNotifications(previous, previousUnreadCount);
        }
    };

    const deleteNotification = async (notificationId: NotificationId): Promise<void> => {
        const previous = [...notifications.value];
        const previousUnreadCount = unreadCount.value;
        const target = notifications.value.find((notification) => notification.id === notificationId);

        notifications.value = notifications.value.filter((notification) => notification.id !== notificationId);
        if (target && !target.is_read) {
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }

        try {
            await axios.delete(`/configuracion/notificaciones/${notificationId}`);
        } catch {
            rollbackNotifications(previous, previousUnreadCount);
        }
    };

    function onNotifVisibilityChange() {
        if (document.visibilityState === 'hidden') {
            notifHiddenAt = Date.now();
            return;
        }
        if (notifHiddenAt === null) return;
        const awayMs = Date.now() - notifHiddenAt;
        notifHiddenAt = null;
        if (awayMs >= NOTIF_AWAY_THRESHOLD_MS) void fetchUnreadCount();
    }

    const startPolling = (): void => {
        if (visibilityListenerActive) return;
        visibilityListenerActive = true;
        document.addEventListener('visibilitychange', onNotifVisibilityChange);
        notifFallbackTimer = setInterval(() => {
            if (document.visibilityState === 'visible') void fetchUnreadCount();
        }, NOTIF_FALLBACK_INTERVAL_MS);
    };

    const stopPolling = (): void => {
        if (!visibilityListenerActive) return;
        visibilityListenerActive = false;
        document.removeEventListener('visibilitychange', onNotifVisibilityChange);
        if (notifFallbackTimer) { clearInterval(notifFallbackTimer); notifFallbackTimer = null; }
    };

    return {
        notifications: readonly(notifications),
        unreadCount: readonly(unreadCount),
        newNotificationsCount: readonly(newNotificationsCount),
        clearNewNotifications: () => { newNotificationsCount.value = 0; },
        loading: readonly(loading),
        fetchUnreadCount,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        archiveNotification,
        deleteNotification,
        formatNotificationDate,
        startPolling,
        stopPolling,
    };
}
