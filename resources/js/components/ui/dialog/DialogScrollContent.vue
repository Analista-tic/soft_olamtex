<script setup lang="ts">
import { cn } from '@/lib/utils';
import { X } from 'lucide-vue-next';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
    type DialogContentEmits,
    type DialogContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<DialogContentProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);

function isAlertStackTarget(target: EventTarget | null) {
    if (!(target instanceof Element)) return false;
    return Boolean(target.closest('[data-alert-stack-root="true"]'));
}

function handlePointerDownOutside(event: unknown) {
    const originalEvent = (event as { detail?: { originalEvent?: MouseEvent } })?.detail?.originalEvent;
    const target = originalEvent?.target ?? null;
    if (isAlertStackTarget(target)) {
        (event as { preventDefault: () => void }).preventDefault();
        return;
    }
    if (!(target instanceof HTMLElement) || !originalEvent) return;
    if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
        (event as { preventDefault: () => void }).preventDefault();
    }
}

function handleInteractOutside(event: unknown) {
    const target = (event as { detail?: { originalEvent?: { target?: EventTarget | null } } })?.detail?.originalEvent?.target ?? null;
    if (isAlertStackTarget(target)) {
        (event as { preventDefault: () => void }).preventDefault();
    }
}
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        >
            <DialogContent
                :class="
                    cn(
                        'relative z-50 my-8 grid w-full max-w-lg gap-4 border border-border bg-background p-6 shadow-lg duration-200 sm:rounded-lg md:w-full',
                        props.class,
                    )
                "
                v-bind="forwarded"
                @pointer-down-outside="handlePointerDownOutside"
                @interact-outside="handleInteractOutside"
            >
                <slot />

                <DialogClose class="absolute right-3 top-3 rounded-md p-0.5 transition-colors hover:bg-secondary">
                    <X class="h-4 w-4" />
                    <span class="sr-only">Close</span>
                </DialogClose>
            </DialogContent>
        </DialogOverlay>
    </DialogPortal>
</template>
