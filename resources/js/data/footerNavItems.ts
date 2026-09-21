import type { NavItem } from '@/domain/shared';
import { FileBarChart2, Settings, ShieldCheck } from 'lucide-vue-next';

export const footerNavItems: NavItem[] = [
    {
        title: 'Informes',
        href: '/informes',
        icon: FileBarChart2,
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
