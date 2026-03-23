export const uiPatterns = {
    typography: {
        pageTitle: 'text-lg font-semibold text-foreground',
        pageSubtitle: 'text-sm text-muted-foreground',
        dialogTitle: 'text-lg font-semibold text-foreground',
        dialogSubtitle: 'text-sm text-muted-foreground',
        fieldLabel: 'text-sm font-medium text-foreground',
        sectionTitle: 'text-sm font-semibold text-foreground',
        sectionSubtitle: 'text-xs text-muted-foreground',
        helperText: 'text-xs text-muted-foreground',
        bodyText: 'text-sm text-foreground',
        bodyMuted: 'text-sm text-muted-foreground',
        bodyStrong: 'text-sm font-medium text-foreground',
        metricLabel: 'text-xs text-muted-foreground',
        metricValue: 'mt-1 text-base font-semibold text-foreground',
        tableHead: 'text-sm font-semibold text-muted-foreground',
    },
    pageIndex: {
        page: 'relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3',
        header: 'flex flex-col gap-1',
        title: 'text-lg font-semibold text-foreground',
        subtitle: 'text-sm text-muted-foreground',
        tableWrap: 'relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min',
        pagination: 'flex justify-center pt-4 pb-2',
    },
    dialogLarge: {
        content: 'max-w-[95vw] w-full max-h-[95vh] h-[95vh] flex flex-col p-0',
        header: 'flex-shrink-0 px-4 pt-4 pb-3 pr-12',
        headerRelative: 'relative flex-shrink-0 px-4 pt-4 pb-3 pr-12',
        body: 'min-h-0 overflow-y-auto flex-1 px-4 pr-2 scroll-smooth',
        footerCompact: 'flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2 px-4 pb-4',
        footerSpaced: 'flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-4 px-4 pb-4',
    },
    dialogMedium: {
        content: 'flex max-h-[90vh] max-w-4xl flex-col overflow-hidden p-0',
        contentNarrow: 'flex max-h-[85vh] max-w-[750px] flex-col overflow-hidden p-0',
        header: 'space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border',
        body: 'min-h-0 flex-1 space-y-4 overflow-y-auto p-5',
        footer: 'flex-shrink-0 justify-end gap-2 border-t border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border',
    },
} as const;

export type UiPatterns = typeof uiPatterns;
