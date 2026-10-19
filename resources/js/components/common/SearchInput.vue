<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';

defineProps<{ placeholder?: string }>();

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <Input v-model="model" :placeholder="placeholder" class="h-10 w-full pl-10" />
        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center px-2">
            <Search :size="20" class="text-muted-foreground" />
        </span>
    </div>
</template>
