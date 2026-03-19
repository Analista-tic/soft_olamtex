export const QUOTE_STATUSES = {
    DRAFT: { value: 'draft', label: 'Borrador', variant: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300' },
    SENT: { value: 'sent', label: 'Enviada', variant: 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300' },
    ACCEPTED: { value: 'accepted', label: 'Aceptada', variant: 'border-emerald-300 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300' },
    REJECTED: { value: 'rejected', label: 'Rechazada', variant: 'border-red-300 bg-red-50/80 text-red-700 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-300' },
    EXPIRED: { value: 'expired', label: 'Vencida', variant: 'border-amber-300 bg-amber-50/80 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-300' },
    CONVERTED: { value: 'converted', label: 'Convertida', variant: 'border-indigo-300 bg-indigo-50/80 text-indigo-700 dark:border-indigo-400/40 dark:bg-indigo-500/10 dark:text-indigo-300' },
} as const;

export const SALES_ORDER_STATUSES = {
    DRAFT: { value: 'draft', label: 'Borrador', variant: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300' },
    CONFIRMED: { value: 'confirmed', label: 'Confirmado', variant: 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300' },
    IN_PROCESS: { value: 'in_process', label: 'En proceso', variant: 'border-amber-300 bg-amber-50/80 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-300' },
    INVOICED: { value: 'invoiced', label: 'Facturado', variant: 'border-emerald-300 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300' },
    CANCELLED: { value: 'cancelled', label: 'Cancelado', variant: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300' },
} as const;

export const SALES_INVOICE_STATUSES = {
    DRAFT: { value: 'draft', label: 'Borrador', variant: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300' },
    ISSUED: { value: 'issued', label: 'Emitida', variant: 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300' },
    PARTIAL: { value: 'partial', label: 'Parcial', variant: 'border-amber-300 bg-amber-50/80 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-300' },
    PAID: { value: 'paid', label: 'Pagada', variant: 'border-emerald-300 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300' },
    VOIDED: { value: 'voided', label: 'Anulada', variant: 'border-red-300 bg-red-50/80 text-red-700 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-300' },
} as const;

export const SALE_NOTE_STATUSES = {
    DRAFT: { value: 'draft', label: 'Borrador', variant: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300' },
    ISSUED: { value: 'issued', label: 'Emitida', variant: 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300' },
    VOIDED: { value: 'voided', label: 'Anulada', variant: 'border-red-300 bg-red-50/80 text-red-700 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-300' },
} as const;
