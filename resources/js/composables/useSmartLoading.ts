import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

export type SmartLoadingTaskId = symbol;

export interface SmartLoadingOptions {
    delay?: number;
    minVisible?: number;
    label?: string;
}

interface SmartLoadingTask {
    delay: number;
    minVisible: number;
    label: string;
    startedAt: number;
    shownAt: number | null;
    shown: boolean;
    delayTimer: ReturnType<typeof setTimeout> | null;
    hideTimer: ReturnType<typeof setTimeout> | null;
}

interface InertiaLikeRouter {
    on: (event: string, callback: (...args: any[]) => void) => void;
}

const DEFAULT_DELAY = 200;
const DEFAULT_MIN_VISIBLE = 350;
const DEFAULT_LABEL = 'Cargando información...';

const tasks = new Map<SmartLoadingTaskId, SmartLoadingTask>();
const isVisible = ref(false);
const activeCount = ref(0);
const label = ref(DEFAULT_LABEL);

let inertiaBound = false;
let inertiaTaskId: SmartLoadingTaskId | null = null;

function refreshState() {
    activeCount.value = tasks.size;

    const visibleTasks = Array.from(tasks.values()).filter((task) => task.shown);
    isVisible.value = visibleTasks.length > 0;

    if (!isVisible.value) {
        label.value = DEFAULT_LABEL;
        return;
    }

    const newestVisibleTask = visibleTasks.reduce((current, candidate) => (candidate.startedAt > current.startedAt ? candidate : current));

    label.value = newestVisibleTask.label || DEFAULT_LABEL;
}

function clearTaskTimers(task: SmartLoadingTask) {
    if (task.delayTimer) {
        clearTimeout(task.delayTimer);
        task.delayTimer = null;
    }
    if (task.hideTimer) {
        clearTimeout(task.hideTimer);
        task.hideTimer = null;
    }
}

function removeTask(id: SmartLoadingTaskId) {
    const task = tasks.get(id);
    if (!task) return;

    clearTaskTimers(task);
    tasks.delete(id);
    refreshState();
}

function start(options: SmartLoadingOptions = {}): SmartLoadingTaskId {
    const id = Symbol('smart-loading');
    const task: SmartLoadingTask = {
        delay: options.delay ?? DEFAULT_DELAY,
        minVisible: options.minVisible ?? DEFAULT_MIN_VISIBLE,
        label: options.label?.trim() || DEFAULT_LABEL,
        startedAt: Date.now(),
        shownAt: null,
        shown: false,
        delayTimer: null,
        hideTimer: null,
    };

    tasks.set(id, task);
    refreshState();

    task.delayTimer = setTimeout(() => {
        const current = tasks.get(id);
        if (!current) return;

        current.delayTimer = null;
        current.shown = true;
        current.shownAt = Date.now();
        refreshState();
    }, task.delay);

    return id;
}

function resolveDefaultStopTask(): SmartLoadingTaskId | null {
    const entries = Array.from(tasks.entries());
    if (!entries.length) return null;

    entries.sort((a, b) => b[1].startedAt - a[1].startedAt);
    return entries[0][0];
}

function stop(id?: SmartLoadingTaskId) {
    const targetId = id ?? resolveDefaultStopTask();
    if (!targetId) return;

    const task = tasks.get(targetId);
    if (!task) return;

    if (task.delayTimer) {
        clearTimeout(task.delayTimer);
        task.delayTimer = null;
    }

    if (!task.shown) {
        removeTask(targetId);
        return;
    }

    const shownAt = task.shownAt ?? Date.now();
    const elapsedVisible = Date.now() - shownAt;
    const remaining = Math.max(0, task.minVisible - elapsedVisible);

    if (remaining <= 0) {
        removeTask(targetId);
        return;
    }

    if (task.hideTimer) return;

    task.hideTimer = setTimeout(() => {
        const current = tasks.get(targetId);
        if (!current) return;

        current.hideTimer = null;
        removeTask(targetId);
    }, remaining);
}

async function run<T>(taskOrFactory: Promise<T> | (() => Promise<T> | T), options: SmartLoadingOptions = {}): Promise<T> {
    const taskId = start(options);

    try {
        if (typeof taskOrFactory === 'function') {
            return await taskOrFactory();
        }
        return await taskOrFactory;
    } finally {
        stop(taskId);
    }
}

function reset() {
    Array.from(tasks.keys()).forEach((id) => removeTask(id));
    inertiaTaskId = null;
}

function bindSmartLoadingToInertia(inertiaRouter: InertiaLikeRouter = router as unknown as InertiaLikeRouter) {
    if (inertiaBound || typeof window === 'undefined') return;
    inertiaBound = true;

    const startNavigationLoading = () => {
        if (inertiaTaskId) return;
        inertiaTaskId = start({ label: 'Cargando página...' });
    };

    const stopNavigationLoading = () => {
        if (!inertiaTaskId) return;
        stop(inertiaTaskId);
        inertiaTaskId = null;
    };

    inertiaRouter.on('start', startNavigationLoading);
    inertiaRouter.on('finish', stopNavigationLoading);
    inertiaRouter.on('error', stopNavigationLoading);
    inertiaRouter.on('invalid', stopNavigationLoading);
    inertiaRouter.on('exception', stopNavigationLoading);
}

const smartLoading = {
    isVisible: readonly(isVisible),
    activeCount: readonly(activeCount),
    label: readonly(label),
    start,
    stop,
    run,
    reset,
    bindSmartLoadingToInertia,
};

export function useSmartLoading() {
    return smartLoading;
}
