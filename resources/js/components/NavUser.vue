<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/domain/shared';
import type { NotificationRow } from '@/domain/Settings';
import type { User } from '@/domain/Settings/types';
import { computed, onMounted, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Archive, BarChart3, Bell, ChevronsUpDown, CheckCheck, ExternalLink, Inbox, Settings, ShieldCheck, X } from 'lucide-vue-next';
import UserMenuContent from './UserMenuContent.vue';
import { useAuthStore } from '@/stores/auth';
import { useInitials } from '@/composables/useInitials';
import axios from 'axios';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const { isMobile, state, setOverlayOpen } = useSidebar();
const auth = useAuthStore();
const { getInitials } = useInitials();

// Computed para saber si está colapsado
const isCollapsed = computed(() => state.value === 'collapsed');

/**
 * Formatea el contador de notificaciones para el badge.
 * - 0 o negativo: null (no mostrar)
 * - 1-99: número como string
 * - 100+: "99+"
 */
const formattedBadgeCount = computed(() => {
    if (unreadCount.value <= 0) return null;
    if (unreadCount.value > 99) return '99+';
    return String(unreadCount.value);
});

// Avatar computed
const avatarSrc = computed(() => (user.avatar ?? '').trim());
const showAvatar = computed(() => avatarSrc.value !== '');

// Estado de notificaciones
const unreadCount = ref(0);
const latestNotifications = ref<NotificationRow[]>([]);
const isNotificationsOpen = ref(false);
const isLoadingNotifications = ref(false);

// Estado del dropdown de perfil (para lock del sidebar)
const isProfileDropdownOpen = ref(false);

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');

const underConstructionHref = (title: string) =>
    route('under-construction', {
        modulo: toSlug(title),
        titulo: title,
    });

const footerNavItems: NavItem[] = [
    {
        title: 'Reportes',
        href: underConstructionHref('Reportes'),
        icon: BarChart3,
    },
    {
        title: 'Auditoría',
        href: '/auditoria',
        icon: ShieldCheck,
        permission: 'ver auditoria',
    },
    {
        title: 'Configuración',
        href: '/configuracion',
        icon: Settings,
        permission: 'ver usuarios',
    },
];

const filteredUserNavItems = computed<NavItem[]>(() =>
    footerNavItems.filter((item) => (item.permission ? auth.can(item.permission) : true)),
);

/**
 * Obtiene el conteo de notificaciones no leídas.
 */
async function fetchUnreadCount() {
    try {
        const response = await axios.get('/configuracion/notificaciones/unread-count');
        unreadCount.value = response.data.count ?? 0;
    } catch (error) {
        console.error('Error al obtener contador de notificaciones:', error);
    }
}

/**
 * Obtiene las últimas notificaciones para el popover.
 */
async function fetchLatestNotifications() {
    isLoadingNotifications.value = true;
    try {
        const response = await axios.get('/configuracion/notificaciones/latest');
        latestNotifications.value = response.data.data ?? [];
    } catch (error) {
        console.error('Error al obtener últimas notificaciones:', error);
    } finally {
        isLoadingNotifications.value = false;
    }
}

/**
 * Marca una notificación como leída y redirige.
 */
async function handleNotificationClick(notification: NotificationRow) {
    if (!notification.is_read) {
        try {
            await axios.post(`/configuracion/notificaciones/${notification.id}/read`);
            notification.is_read = true;
            notification.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch (error) {
            console.error('Error al marcar como leída:', error);
        }
    }
    
    isNotificationsOpen.value = false;
    
    if (notification.url) {
        router.visit(notification.url);
    }
}

/**
 * Marca todas como leídas.
 */
async function markAllAsRead() {
    try {
        await axios.post('/configuracion/notificaciones/read-all');
        latestNotifications.value = latestNotifications.value.map((n) => ({
            ...n,
            is_read: true,
            read_at: n.read_at ?? new Date().toISOString(),
        }));
        unreadCount.value = 0;
    } catch (error) {
        console.error('Error al marcar todas como leídas:', error);
    }
}

/**
 * Navega a la página de notificaciones.
 */
function goToNotifications() {
    isNotificationsOpen.value = false;
    router.visit('/configuracion/notificaciones');
}

/**
 * Archiva una notificación (optimista).
 */
async function archiveNotification(notification: NotificationRow, event: Event) {
    event.stopPropagation();
    
    // Guardar estado para restaurar en caso de error
    const previousList = [...latestNotifications.value];
    const wasUnread = !notification.is_read;
    
    // Actualización optimista
    latestNotifications.value = latestNotifications.value.filter(n => n.id !== notification.id);
    if (wasUnread) {
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    
    try {
        await axios.patch(`/configuracion/notificaciones/${notification.id}/archive`);
    } catch (error) {
        // Restaurar estado anterior si falla
        latestNotifications.value = previousList;
        if (wasUnread) {
            unreadCount.value += 1;
        }
        console.error('Error al archivar notificación:', error);
    }
}

/**
 * Elimina una notificación (optimista).
 */
async function deleteNotification(notification: NotificationRow, event: Event) {
    event.stopPropagation();
    
    // Guardar estado para restaurar en caso de error
    const previousList = [...latestNotifications.value];
    const wasUnread = !notification.is_read;
    
    // Actualización optimista
    latestNotifications.value = latestNotifications.value.filter(n => n.id !== notification.id);
    if (wasUnread) {
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    
    try {
        await axios.delete(`/configuracion/notificaciones/${notification.id}`);
    } catch (error) {
        // Restaurar estado anterior si falla
        latestNotifications.value = previousList;
        if (wasUnread) {
            unreadCount.value += 1;
        }
        console.error('Error al eliminar notificación:', error);
    }
}

/**
 * Formatea fecha relativa.
 */
function formatRelativeDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Ahora';
    if (diffMins < 60) return `Hace ${diffMins} min`;
    if (diffHours < 24) return `Hace ${diffHours}h`;
    if (diffDays < 7) return `Hace ${diffDays}d`;
    
    return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: 'short',
    }).format(date);
}

/**
 * Cuando se abre el popover, cargar notificaciones.
 */
function onPopoverOpenChange(open: boolean) {
    isNotificationsOpen.value = open;
    setOverlayOpen(open); // Lock sidebar while popover is open
    if (open) {
        fetchLatestNotifications();
    }
}

/**
 * Handler para el dropdown de perfil.
 */
function onProfileDropdownOpenChange(open: boolean) {
    isProfileDropdownOpen.value = open;
    setOverlayOpen(open); // Lock sidebar while dropdown is open
}

// Cargar contador al montar
onMounted(() => {
    fetchUnreadCount();
});
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <!-- ===== MODO COLAPSADO: Botones cuadrados centrados ===== -->
            <div v-if="isCollapsed" class="flex flex-col items-center gap-2 w-full">
                <!-- Botón Notificaciones (Campana) con Popover - PRIMERO -->
                <Popover :open="isNotificationsOpen" @update:open="onPopoverOpenChange">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <PopoverTrigger as-child>
                                <button
                                    class="relative flex items-center justify-center h-10 w-10 rounded-md hover:bg-sidebar-accent transition-colors"
                                    aria-label="Notificaciones"
                                >
                                    <!-- Wrapper fijo para el ícono + badge -->
                                    <span class="relative inline-flex items-center justify-center">
                                        <Bell class="size-[18px]" />
                                        <Badge
                                            v-if="formattedBadgeCount"
                                            variant="destructive"
                                            class="notification-badge absolute -top-2 -right-2.5 h-4 min-w-4 px-1 flex items-center justify-center text-[10px] leading-none font-medium"
                                        >
                                            {{ formattedBadgeCount }}
                                        </Badge>
                                    </span>
                                </button>
                            </PopoverTrigger>
                        </TooltipTrigger>
                        <TooltipContent side="right" align="center">
                            Notificaciones
                        </TooltipContent>
                    </Tooltip>
                    <PopoverContent 
                        class="w-[360px] p-0" 
                        side="right"
                        :side-offset="8"
                        align="start"
                    >
                        <!-- Header del Popover -->
                        <div class="flex items-center justify-between px-4 py-3 border-b">
                            <h4 class="font-semibold text-sm">Notificaciones</h4>
                            <Button
                                v-if="unreadCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                @click="markAllAsRead"
                            >
                                <CheckCheck class="h-3 w-3 mr-1" />
                                Leer todas
                            </Button>
                        </div>

                        <!-- Lista de Notificaciones -->
                        <div class="max-h-80 overflow-y-auto">
                            <div v-if="isLoadingNotifications" class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary"></div>
                            </div>
                            <div v-else-if="latestNotifications.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                <Inbox class="h-8 w-8 mb-2 opacity-50" />
                                <p class="text-xs">No hay notificaciones</p>
                            </div>
                            <template v-else>
                                <div
                                    v-for="notification in latestNotifications"
                                    :key="notification.id"
                                    class="notification-item group relative border-b last:border-b-0 overflow-hidden"
                                    :class="{ 'bg-primary/5': !notification.is_read }"
                                >
                                    <!-- Contenido principal - se desplaza al hover -->
                                    <button
                                        class="notification-content w-full text-left px-4 py-3 transition-transform duration-150 ease-out group-hover:-translate-x-2"
                                        @click="handleNotificationClick(notification)"
                                    >
                                        <div class="flex items-start gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium truncate" :class="{ 'text-primary': !notification.is_read }">
                                                    {{ notification.title ?? 'Notificación' }}
                                                </p>
                                                <p class="text-xs text-muted-foreground line-clamp-2 mt-0.5">
                                                    {{ notification.body }}
                                                </p>
                                                <span class="text-[10px] text-muted-foreground mt-1 block">
                                                    {{ formatRelativeDate(notification.created_at) }}
                                                </span>
                                            </div>
                                            <ExternalLink v-if="notification.url" class="h-3 w-3 text-muted-foreground shrink-0 mt-1" />
                                        </div>
                                    </button>
                                    
                                    <!-- Acciones hover (posición absoluta) -->
                                    <div 
                                        class="notification-actions absolute right-0 top-0 bottom-0 flex items-center gap-0.5 px-2 bg-gradient-to-l from-background via-background to-transparent opacity-0 pointer-events-none transition-opacity duration-150 ease-out group-hover:opacity-100 group-hover:pointer-events-auto"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    class="h-7 w-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                                                    @click="archiveNotification(notification, $event)"
                                                >
                                                    <Archive class="h-3.5 w-3.5" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" :side-offset="4">
                                                Archivar
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    class="h-7 w-7 flex items-center justify-center rounded-md hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors"
                                                    @click="deleteNotification(notification, $event)"
                                                >
                                                    <X class="h-3.5 w-3.5" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" :side-offset="4">
                                                Eliminar
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="border-t px-4 py-2">
                            <Button variant="ghost" size="sm" class="w-full text-xs" @click="goToNotifications">
                                Ver todas las notificaciones
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>

                <!-- Botón Usuario (Avatar) con Dropdown - SEGUNDO -->
                <DropdownMenu @update:open="onProfileDropdownOpenChange">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <DropdownMenuTrigger as-child>
                                <button
                                    class="flex items-center justify-center h-10 w-10 rounded-md hover:bg-sidebar-accent transition-colors"
                                    aria-label="Perfil"
                                >
                                    <Avatar class="h-7 w-7 overflow-hidden rounded-md">
                                        <AvatarImage v-if="showAvatar" :src="avatarSrc" :alt="user.name" />
                                        <AvatarFallback class="rounded-md text-black dark:text-white text-xs">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </button>
                            </DropdownMenuTrigger>
                        </TooltipTrigger>
                        <TooltipContent side="right" align="center">
                            Perfil
                        </TooltipContent>
                    </Tooltip>
                    <DropdownMenuContent 
                        class="min-w-56 rounded-lg" 
                        side="right"
                        align="end" 
                        :side-offset="8"
                    >
                        <UserMenuContent :user="user" :items="filteredUserNavItems" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- ===== MODO EXPANDIDO: Layout original ===== -->
            <div v-else class="flex items-center gap-1 w-full">
                <!-- Usuario + Dropdown -->
                <DropdownMenu @update:open="onProfileDropdownOpenChange">
                    <DropdownMenuTrigger as-child>
                        <SidebarMenuButton size="lg" class="flex-1 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <UserInfo :user="user" />
                            <ChevronsUpDown class="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent 
                        class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg" 
                        side="bottom"
                        align="end" 
                        :side-offset="4"
                    >
                        <UserMenuContent :user="user" :items="filteredUserNavItems" />
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Botón de Notificaciones -->
                <Popover :open="isNotificationsOpen" @update:open="onPopoverOpenChange">
                    <PopoverTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative h-9 w-9 shrink-0 rounded-md hover:bg-sidebar-accent"
                            aria-label="Notificaciones"
                        >
                            <!-- Wrapper fijo para el ícono + badge -->
                            <span class="relative inline-flex items-center justify-center">
                                <Bell class="size-[18px]" />
                                <Badge
                                    v-if="formattedBadgeCount"
                                    variant="destructive"
                                    class="notification-badge absolute -top-2 -right-2.5 h-4 min-w-4 px-1 flex items-center justify-center text-[10px] leading-none font-medium"
                                >
                                    {{ formattedBadgeCount }}
                                </Badge>
                            </span>
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent 
                        class="w-[360px] p-0" 
                        :side="isMobile ? 'top' : 'right'"
                        :side-offset="8"
                        align="end"
                    >
                        <!-- Header del Popover -->
                        <div class="flex items-center justify-between px-4 py-3 border-b">
                            <h4 class="font-semibold text-sm">Notificaciones</h4>
                            <Button
                                v-if="unreadCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                @click="markAllAsRead"
                            >
                                <CheckCheck class="h-3 w-3 mr-1" />
                                Leer todas
                            </Button>
                        </div>

                        <!-- Lista de Notificaciones -->
                        <div class="max-h-80 overflow-y-auto">
                            <div v-if="isLoadingNotifications" class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary"></div>
                            </div>
                            <div v-else-if="latestNotifications.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                <Inbox class="h-8 w-8 mb-2 opacity-50" />
                                <p class="text-xs">No hay notificaciones</p>
                            </div>
                            <template v-else>
                                <div
                                    v-for="notification in latestNotifications"
                                    :key="notification.id"
                                    class="notification-item group relative border-b last:border-b-0 overflow-hidden"
                                    :class="{ 'bg-primary/5': !notification.is_read }"
                                >
                                    <!-- Contenido principal - se desplaza al hover -->
                                    <button
                                        class="notification-content w-full text-left px-4 py-3 transition-transform duration-150 ease-out group-hover:-translate-x-2"
                                        @click="handleNotificationClick(notification)"
                                    >
                                        <div class="flex items-start gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium truncate" :class="{ 'text-primary': !notification.is_read }">
                                                    {{ notification.title ?? 'Notificación' }}
                                                </p>
                                                <p class="text-xs text-muted-foreground line-clamp-2 mt-0.5">
                                                    {{ notification.body }}
                                                </p>
                                                <span class="text-[10px] text-muted-foreground mt-1 block">
                                                    {{ formatRelativeDate(notification.created_at) }}
                                                </span>
                                            </div>
                                            <ExternalLink v-if="notification.url" class="h-3 w-3 text-muted-foreground shrink-0 mt-1" />
                                        </div>
                                    </button>
                                    
                                    <!-- Acciones hover (posición absoluta) -->
                                    <div 
                                        class="notification-actions absolute right-0 top-0 bottom-0 flex items-center gap-0.5 px-2 bg-gradient-to-l from-background via-background to-transparent opacity-0 pointer-events-none transition-opacity duration-150 ease-out group-hover:opacity-100 group-hover:pointer-events-auto"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    class="h-7 w-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                                                    @click="archiveNotification(notification, $event)"
                                                >
                                                    <Archive class="h-3.5 w-3.5" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" :side-offset="4">
                                                Archivar
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    class="h-7 w-7 flex items-center justify-center rounded-md hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors"
                                                    @click="deleteNotification(notification, $event)"
                                                >
                                                    <X class="h-3.5 w-3.5" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" :side-offset="4">
                                                Eliminar
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="border-t px-4 py-2">
                            <Button variant="ghost" size="sm" class="w-full text-xs" @click="goToNotifications">
                                Ver todas las notificaciones
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </SidebarMenuItem>
    </SidebarMenu>
</template>

