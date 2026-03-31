import { PackageCheck, PackageSearch } from 'lucide-vue-next';
import type { ScopeOption } from './types';

export const SCOPE_OPTIONS: ReadonlyArray<ScopeOption> = [
    { value: 'muestra', label: 'Muestras', icon: PackageSearch },
    { value: 'producto', label: 'Productos', icon: PackageCheck },
];

export const TAB_COLORS = [
    'bg-red-100 text-red-800 data-[state=active]:bg-red-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-rose-100 text-rose-800 data-[state=active]:bg-rose-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-pink-100 text-pink-800 data-[state=active]:bg-pink-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-orange-100 text-orange-800 data-[state=active]:bg-orange-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-amber-100 text-amber-800 data-[state=active]:bg-amber-600 data-[state=active]:text-amber-100 data-[state=active]:shadow-md',
    'bg-yellow-100 text-yellow-800 data-[state=active]:bg-yellow-600 data-[state=active]:text-yellow-100 data-[state=active]:shadow-md',
    'bg-lime-100 text-lime-800 data-[state=active]:bg-lime-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-green-100 text-green-800 data-[state=active]:bg-green-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-emerald-100 text-emerald-800 data-[state=active]:bg-emerald-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-teal-100 text-teal-800 data-[state=active]:bg-teal-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-cyan-100 text-cyan-800 data-[state=active]:bg-cyan-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-blue-100 text-blue-800 data-[state=active]:bg-blue-600 data-[state=active]:text-white data-[state=active]:shadow-md',
    'bg-indigo-100 text-indigo-800 data-[state=active]:bg-indigo-600 data-[state=active]:text-white data-[state=active]:shadow-md',
] as const;

export const TOGGLE_GROUP_BASE = 'inline-flex min-w-max items-center gap-1 rounded-full bg-muted/40 p-1';
export const TOGGLE_ITEM_BASE =
    'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition text-muted-foreground hover:text-foreground data-[state=on]:border-primary/30 data-[state=on]:bg-primary/10 data-[state=on]:text-primary data-[state=on]:shadow-sm';
export const ACTION_BUTTON_CLASS =
    'inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';
export const ACTION_ICON_CLASS = 'h-3.5 w-3.5';
