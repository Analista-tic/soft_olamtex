<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import type { User } from '@/domain/shared/types';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import PasswordDialog from './PasswordDialog.vue';

const props = defineProps<{
    user?: User;
}>();

const emit = defineEmits<{
    (e: 'success', message: string): void;
    (e: 'error', message: string): void;
}>();

const profileInfo = reactive({
    username: '',
    email: '',
});

const profileSaving = ref(false);
const isPasswordModalOpen = ref(false);
const submitAttempted = ref(false);

const syncProfileInfo = () => {
    profileInfo.username = props.user?.name ?? '';
    profileInfo.email = props.user?.email ?? '';
};
watch(() => props.user, syncProfileInfo, { immediate: true });

const areaName = computed(() => props.user?.area?.name || 'Sin área asignada');
const roleNames = computed(() => {
    const names = (props.user?.roles ?? []).map((role) => role.name).filter(Boolean);
    return names.length > 0 ? names.join(', ') : 'Sin rol asignado';
});

/** Única fuente de "campo faltante": la usan el mensaje general y el template. */
const missingFields = computed(() => {
    const missing: Array<'username' | 'email'> = [];
    if (!profileInfo.username.trim()) missing.push('username');
    if (!profileInfo.email.trim()) missing.push('email');
    return missing;
});
const isMissing = (field: 'username' | 'email') => submitAttempted.value && missingFields.value.includes(field);

const openPasswordModal = () => {
    isPasswordModalOpen.value = true;
};

const handlePasswordSaved = (message: string) => {
    emit('success', message);
};

const handlePasswordError = (message: string) => {
    emit('error', message);
};

async function saveProfileInfo() {
    submitAttempted.value = true;
    if (missingFields.value.length > 0) {
        emit('error', 'Completa los campos requeridos: nombre de usuario y correo electrónico.');
        return;
    }

    profileSaving.value = true;
    try {
        const { data } = await axios.patch(route('profile.update'), {
            name: profileInfo.username.trim(),
            email: profileInfo.email.trim(),
        });
        submitAttempted.value = false;
        router.reload({ only: ['auth'] });
        emit('success', data?.message ?? 'Perfil actualizado correctamente.');
    } catch (error: unknown) {
        const response = axios.isAxiosError(error) ? error.response?.data : null;
        const firstError = response?.errors ? (Object.values(response.errors)[0] as string[] | undefined)?.[0] : null;
        emit('error', firstError || response?.message || 'No se pudo actualizar el perfil.');
    } finally {
        profileSaving.value = false;
    }
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="saveProfileInfo">
        <div class="flex items-start gap-4">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold text-foreground">Perfil del usuario</h2>
                <p class="text-sm text-muted-foreground">Edita tu nombre de usuario, correo y contraseña. El área y el rol los asigna un administrador.</p>
            </div>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Nombre de usuario<span class="text-red-500">*</span></label>
            <Input
                v-model="profileInfo.username"
                placeholder="Nombre de usuario"
                :class="{ 'border-red-500 focus-visible:ring-red-500': isMissing('username') }"
            />
            <p v-if="isMissing('username')" class="text-xs text-red-500">Requerido</p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Correo electrónico<span class="text-red-500">*</span></label>
            <Input
                v-model="profileInfo.email"
                data-no-uppercase
                placeholder="correo@dominio.com"
                :class="{ 'border-red-500 focus-visible:ring-red-500': isMissing('email') }"
            />
            <p v-if="isMissing('email')" class="text-xs text-red-500">Requerido</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium text-foreground">Área</label>
                <Input :model-value="areaName" data-no-uppercase readonly disabled class="bg-muted/40" />
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium text-foreground">Rol</label>
                <Input :model-value="roleNames" data-no-uppercase readonly disabled class="bg-muted/40" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                @click="openPasswordModal"
            >
                Actualizar contraseña
            </button>
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="profileSaving"
            >
                {{ profileSaving ? 'Guardando...' : 'Guardar cambios' }}
            </button>
        </div>

        <PasswordDialog v-model="isPasswordModalOpen" @saved="handlePasswordSaved" @error="handlePasswordError" />
    </form>
</template>
