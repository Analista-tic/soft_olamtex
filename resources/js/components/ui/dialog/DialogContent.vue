<script setup lang="ts">
import { cn } from '@/lib/utils';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
    type DialogContentEmits,
    type DialogContentProps,
} from 'radix-vue';
import { computed, useAttrs, type HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<
    DialogContentProps & {
        class?: HTMLAttributes['class'];
        closeClass?: HTMLAttributes['class'];
        contentClass?: HTMLAttributes['class'];
        overlayClass?: HTMLAttributes['class'];
        scrollable?: boolean;
    }
>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const {
        class: _class,
        closeClass: _closeClass,
        contentClass: _contentClass,
        overlayClass: _overlayClass,
        scrollable: _scrollable,
        ...delegated
    } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
const attrs = useAttrs();
const mergedForwarded = computed(() => ({ ...forwarded, ...attrs }));
const isScrollable = computed(() => props.scrollable ?? true);

function isDatepickerPopoverTarget(target: EventTarget | null) {
    if (!(target instanceof Element)) return false;
    return Boolean(target.closest('[data-datepicker-popover="true"]'));
}

function handlePointerDownOutside(event: unknown) {
    const target = (event as { detail?: { originalEvent?: { target?: EventTarget | null } } })?.detail?.originalEvent?.target ?? null;
    if (isDatepickerPopoverTarget(target)) {
        (event as { preventDefault: () => void }).preventDefault();
    }
}

function handleInteractOutside(event: unknown) {
    const target = (event as { detail?: { originalEvent?: { target?: EventTarget | null } } })?.detail?.originalEvent?.target ?? null;
    if (isDatepickerPopoverTarget(target)) {
        (event as { preventDefault: () => void }).preventDefault();
    }
}
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            :class="
                cn(
                    'fixed inset-0 z-50 bg-black/80 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    props.overlayClass,
                )
            "
        />
        <DialogContent
            v-bind="mergedForwarded"
            @pointer-down-outside="handlePointerDownOutside"
            @interact-outside="handleInteractOutside"
            :class="
                cn(
                    'h-fit max-h-[90vh] w-[calc(100%-2rem)] max-w-4xl overflow-y-auto border border-border bg-background shadow-[0_25px_70px_rgba(15,23,42,0.35)] sm:rounded-[28px]',
                    props.class,
                    'fixed left-1/2 top-1/2 z-[51] -translate-x-1/2 -translate-y-1/2',
                )
            "
        >
            <div class="flex max-h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-[10px]">
                <div
                    :class="cn(
                        'dialog-scroll-area flex min-h-0 flex-1 flex-col scroll-smooth',
                        isScrollable ? 'overflow-y-auto px-3 py-4' : 'px-3 py-2',
                        props.contentClass,
                    )"
                >
                    <slot />
                </div>
            </div>

            <DialogClose
                :class="
                    cn(
                        'absolute right-5 top-2 z-10 rounded border-0 bg-transparent px-2 py-1 text-lg font-medium text-muted-foreground transition hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                        props.closeClass,
                    )
                "
            >
                <span aria-hidden="true">&times;</span>
                <span class="sr-only">Close</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>

<style scoped>
.dialog-scroll-area {
    scrollbar-width: thin;
}

.dialog-scroll-area::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
</style>
