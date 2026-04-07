import { computed, ref } from 'vue';

type BooleanSource = {
    readonly value: boolean;
};

export function useDialogLoadingState(dialogOpen: BooleanSource) {
    const isBootstrapping = ref(false);
    const isHydrating = ref(false);
    const loadingRun = ref(0);

    const isBlockingLoading = computed(() => dialogOpen.value && (isBootstrapping.value || isHydrating.value));
    const canRenderForm = computed(() => !isBlockingLoading.value);

    function startLoadingRun(): number {
        loadingRun.value += 1;
        return loadingRun.value;
    }

    function isCurrentLoadingRun(run: number): boolean {
        return run === loadingRun.value && dialogOpen.value;
    }

    function cancelLoadingRun(): void {
        loadingRun.value += 1;
        isBootstrapping.value = false;
        isHydrating.value = false;
    }

    return {
        isBootstrapping,
        isHydrating,
        isBlockingLoading,
        canRenderForm,
        startLoadingRun,
        isCurrentLoadingRun,
        cancelLoadingRun,
    };
}
