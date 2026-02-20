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
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<
    DialogContentProps & {
        class?: HTMLAttributes['class'];
        closeClass?: HTMLAttributes['class'];
        overlayClass?: HTMLAttributes['class'];
        scrollable?: boolean;
    }
>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, closeClass: __, overlayClass: ___, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
const isScrollable = computed(() => props.scrollable ?? true);
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
            v-bind="forwarded"
            :class="
                cn(
                    'fixed inset-0 z-50 m-auto h-fit max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-4xl border border-border bg-background shadow-[0_25px_70px_rgba(15,23,42,0.35)] duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 sm:rounded-[28px]',
                    props.class,
                )
            "
        >
            <div class="flex h-full max-h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-[10px]">
                <div
                    :class="cn(
                        'dialog-scroll-area flex min-h-0 flex-1 flex-col scroll-smooth',
                        isScrollable ? 'overflow-y-auto px-3 py-4' : 'px-3 py-2',
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

