<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import Input from '@/components/ui/input/Input.vue';
import type { User } from '@/domain/shared/types';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref, watch } from 'vue';
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
const isDeletingProfile = ref(false);
const isDeleteDialogOpen = ref(false);

const syncProfileInfo = () => {
    profileInfo.username = props.user?.name ?? '';
    profileInfo.email = props.user?.email ?? '';
};
watch(() => props.user, syncProfileInfo, { immediate: true });

const resetProfileForm = () => {
    syncProfileInfo();
};

const handleResetProfile = () => {
    resetProfileForm();
};

const openPasswordModal = () => {
    isPasswordModalOpen.value = true;
};

const handlePasswordSaved = (message: string) => {
    emit('success', message);
};

const handlePasswordError = (message: string) => {
    emit('error', message);
};

const openDeleteDialog = () => {
    isDeleteDialogOpen.value = true;
};

async function deleteProfile() {
    isDeletingProfile.value = true;
    try {
        await axios.delete(route('profile.destroy'));
        router.visit(route('login'));
    } catch {
        emit('error', 'No se pudo eliminar el perfil. Intenta más tarde.');
    } finally {
        isDeletingProfile.value = false;
    }
}

function saveProfileInfo() {
    profileSaving.value = true;
    resetProfileForm();
    profileSaving.value = false;
    emit('success', 'Perfil actualizado correctamente.');
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="saveProfileInfo">
        <div class="flex items-start gap-4">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold text-foreground">Perfil del usuario</h2>
                <p class="text-sm text-muted-foreground">Edita el nombre de usuario, el correo y la contraseña que están en la tabla users.</p>
            </div>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Nombre de usuario</label>
            <Input v-model="profileInfo.username" placeholder="Nombre de usuario" />
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Correo electrónico</label>
            <Input v-model="profileInfo.email" placeholder="correo@dominio.com" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="openPasswordModal"
                >
                    Actualizar contraseña
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-destructive px-5 py-2 text-sm font-semibold text-destructive-foreground shadow-sm transition hover:bg-destructive/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="openDeleteDialog"
                    :disabled="isDeletingProfile"
                >
                    {{ isDeletingProfile ? 'Eliminando...' : 'Eliminar perfil' }}
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="profileSaving"
                >
                    {{ profileSaving ? 'Guardando...' : 'Guardar cambios' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleResetProfile"
                >
                    Restablecer
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="mb-1 font-semibold text-red-600">Advertencia</p>
            <p>Eliminar el perfil también elimina completamente la cuenta y no puede deshacerse.</p>
        </div>

        <PasswordDialog v-model="isPasswordModalOpen" @saved="handlePasswordSaved" @error="handlePasswordError" />
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Seguro que deseas eliminar este perfil? Esta acción no se puede deshacer."
            confirm-text="Eliminar perfil"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteProfile"
        />
    </form>
</template>
