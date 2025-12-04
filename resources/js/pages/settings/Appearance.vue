<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import AlertBox from '@/components/AlertBox.vue';
import AppearanceSection from '@/pages/Configuration/BasicData/Appearance.vue';
import { ref } from 'vue';

const breadcrumbItems = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];

const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('success');

const clearAlerts = () => {
    successMessage.value = '';
    errorMessage.value = '';
};

const handleSuccess = (message: string) => {
    successMessage.value = message;
    errorMessage.value = '';
    errorType.value = 'success';
};

const handleError = (message: string) => {
    errorMessage.value = message;
    successMessage.value = '';
    errorType.value = 'error';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />
        <div class="relative flex flex-1 flex-col gap-3">
            <transition name="fade">
                <div
                    v-if="successMessage || errorMessage"
                    class="pointer-events-none fixed left-1/2 top-4 z-[9999] flex w-full max-w-xl -translate-x-1/2 px-3"
                >
                    <AlertBox
                        class="pointer-events-auto w-full"
                        :type="errorMessage ? errorType : 'success'"
                        :message="errorMessage || successMessage"
                        :title="errorMessage ? 'Error' : '¡Listo!'"
                        :auto-close-ms="0"
                        @close="clearAlerts"
                    />
                </div>
            </transition>
            <SettingsLayout>
                <AppearanceSection @success="handleSuccess" @error="handleError" />
            </SettingsLayout>
        </div>
    </AppLayout>
</template>
