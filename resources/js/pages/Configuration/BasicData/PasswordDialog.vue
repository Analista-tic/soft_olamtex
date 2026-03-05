<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import axios from 'axios';
import { reactive, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
    }>(),
    {
        modelValue: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'saved', message: string): void;
    (e: 'error', message: string): void;
}>();

const form = reactive({
    current_password: '',
    new_password: '',
    confirm_password: '',
});

const saving = ref(false);
const message = ref('');
const messageType = ref<'success' | 'error'>('success');

const resetForm = () => {
    form.current_password = '';
    form.new_password = '';
    form.confirm_password = '';
    message.value = '';
};

watch(
    () => props.modelValue,
    (visible) => {
        if (visible) {
            resetForm();
        }
    },
);

const close = () => {
    emit('update:modelValue', false);
    resetForm();
};

const handleSubmit = async () => {
    saving.value = true;
    message.value = '';
    try {
        await axios.put(route('password.update'), {
            current_password: form.current_password,
            new_password: form.new_password,
            new_password_confirmation: form.confirm_password,
        });
        messageType.value = 'success';
        message.value = 'Contraseña actualizada correctamente.';
        emit('saved', message.value);
        close();
    } catch (error) {
        messageType.value = 'error';
        if (axios.isAxiosError(error) && error.response?.data?.errors) {
            const firstError = (Object.values(error.response.data.errors) as any[])[0]?.[0];
            message.value = firstError ?? 'No se pudo actualizar la contraseña.';
        } else if (axios.isAxiosError(error) && error.response?.data?.message) {
            message.value = error.response.data.message;
        } else {
            message.value = 'No se pudo actualizar la contraseña.';
        }
        emit('error', message.value);
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog :open="modelValue" @update:open="close">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[85vh] px-2 py-2">
            <form class="space-y-4" @submit.prevent="handleSubmit">
                <DialogHeader>
                    <DialogTitle>Actualizar contraseña</DialogTitle>
                    <DialogDescription>Asegúrate de usar una contraseña larga y segura.</DialogDescription>
                </DialogHeader>
                <fieldset class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Contraseña actual</label>
                        <Input v-model="form.current_password" type="password" placeholder="Contraseña actual" required />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Nueva contraseña</label>
                        <Input v-model="form.new_password" type="password" placeholder="Nueva contraseña" required />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Confirmar contraseña</label>
                        <Input v-model="form.confirm_password" type="password" placeholder="Repite la contraseña" required />
                    </div>
                    <p v-if="message" :class="messageType === 'success' ? 'text-emerald-600' : 'text-red-600'" class="text-sm font-medium">
                        {{ message }}
                    </p>
                </fieldset>
                <DialogFooter class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="pill"
                        class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                        @click="close"
                        :disabled="saving"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" size="pill" :disabled="saving">
                        {{ saving ? 'Guardando...' : 'Guardar contraseña' }}
                    </Button>
                </DialogFooter>
            </form>
        </FormDialogContent>
    </Dialog>
</template>
