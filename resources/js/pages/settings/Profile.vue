<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AlertBox from '@/components/AlertBox.vue';
import ProfileSection from '@/pages/Configuration/BasicData/Profile.vue';
import type { SharedData } from '@/types';
import { ref } from 'vue';

const breadcrumbs = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user;

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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />
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
                <ProfileSection :user="user" @success="handleSuccess" @error="handleError" />
            </SettingsLayout>
        </div>
    </AppLayout>
</template>
