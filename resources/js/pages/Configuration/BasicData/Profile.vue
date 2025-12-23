<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PasswordDialog from './PasswordDialog.vue';
import axios from 'axios';
import { Pencil } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import type { SharedData } from '@/types';

const props = defineProps<{
    user?: SharedData['auth']['user'];
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
const isDeletingProfile = ref(false);
const isEditingProfile = ref(false);
const isPasswordModalOpen = ref(false);
const isDeleteDialogOpen = ref(false);

const syncProfileInfo = () => {
    profileInfo.username = props.user?.name ?? '';
    profileInfo.email = props.user?.email ?? '';
};
watch(
    () => props.user,
    syncProfileInfo,
    { immediate: true },
);

const enterEditMode = () => {
    isEditingProfile.value = true;
};

const exitEditMode = () => {
    isEditingProfile.value = false;
};

const resetProfileForm = () => {
    syncProfileInfo();
};

const handleCancelEdit = () => {
    resetProfileForm();
    exitEditMode();
};

function saveProfileInfo() {
    profileSaving.value = true;
    resetProfileForm();
    exitEditMode();
    profileSaving.value = false;
    emit('success', 'Perfil actualizado correctamente.');
}

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

const openPasswordModal = () => {
    isPasswordModalOpen.value = true;
};

const openDeleteDialog = () => {
    isDeleteDialogOpen.value = true;
};

const handlePasswordSaved = (message: string) => {
    emit('success', message);
};
const handlePasswordError = (message: string) => {
    emit('error', message);
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="saveProfileInfo">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold text-slate-900">
                    {{ isEditingProfile ? 'Editar perfil del usuario' : 'Perfil del usuario' }}
                </h2>
                <p class="text-sm text-slate-500">
                    Edita el nombre de usuario, el correo y la contraseña que están en la tabla users.
                </p>
            </div>
            <button
                v-if="!isEditingProfile"
                type="button"
                class="inline-flex items-center gap-1 rounded-full border border-blue-100 bg-white px-3 py-1 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50"
                @click="enterEditMode"
            >
                <Pencil class="h-4 w-4" />
                Editar
            </button>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Nombre de usuario</label>
            <Input v-model="profileInfo.username" placeholder="Nombre de usuario" :readonly="!isEditingProfile" />
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Correo electrónico</label>
            <Input v-model="profileInfo.email" placeholder="correo@dominio.com" :readonly="!isEditingProfile" />
        </div>

        <div class="flex flex-wrap gap-3 border-t pt-4 items-center">
            <Button
                v-if="isEditingProfile"
                type="submit"
                variant="default"
                :disabled="profileSaving"
            >
                {{ profileSaving ? 'Guardando...' : 'Actualizar perfil' }}
            </Button>
            <Button
                v-if="isEditingProfile"
                type="button"
                variant="outline"
                class="text-slate-600"
                @click="handleCancelEdit"
            >
                Cancelar
            </Button>
            <Button
                v-if="!isEditingProfile"
                type="button"
                variant="default"
                @click="openPasswordModal"
            >
                Actualizar contraseña
            </Button>
            <Button
                v-if="!isEditingProfile"
                type="button"
                variant="destructive"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold shadow-sm transition"
                @click="openDeleteDialog"
                :disabled="isDeletingProfile"
            >
                {{ isDeletingProfile ? 'Eliminando...' : 'Eliminar perfil' }}
            </Button>
        </div>
        <div
            v-if="!isEditingProfile"
            class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
            <p class="font-semibold text-red-600 mb-1">Advertencia</p>
            <p>Eliminar el perfil también elimina completamente la cuenta y no puede deshacerse.</p>
        </div>

        <PasswordDialog
            v-model="isPasswordModalOpen"
            @saved="handlePasswordSaved"
            @error="handlePasswordError"
        />

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
