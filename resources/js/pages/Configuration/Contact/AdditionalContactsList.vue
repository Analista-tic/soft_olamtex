<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import Button from '@/components/ui/button/Button.vue';
import AlertBox from '@/components/AlertBox.vue';
import { PlusCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    additionalContacts: { name: string; email: string; phone: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:additionalContacts', updatedContacts: { name: string; email: string; phone: string }[]): void;
}>();

// Estado interno para los contactos adicionales
const internalContacts = ref([...props.additionalContacts]);

// Mensaje de alerta (cajita roja arriba del form)
const alertMessage = ref<string>('');

// Validar que todos los campos de los contactos estén llenos
const validateContact = (contact: { name: string; email: string; phone: string }) => {
    const errors = {
        name: !contact.name.trim() ? 'El nombre es obligatorio.' : '',
        email: !contact.email.trim() ? 'El correo es obligatorio.' : '',
        phone: !contact.phone.trim() ? 'El teléfono es obligatorio.' : '',
    };
    return errors;
};

// Obtener errores para todos los contactos
const contactErrors = ref(internalContacts.value.map((contact) => validateContact(contact)));

// Función para detectar duplicados (nombre + correo + teléfono)
const hasDuplicateContact = (
    contacts: { name: string; email: string; phone: string }[]
): boolean => {
    const seen = new Set<string>();
    for (const c of contacts) {
        const key = `${c.name.trim().toLowerCase()}|${c.email.trim().toLowerCase()}|${c.phone.trim()}`;
        if (seen.has(key)) return true;
        seen.add(key);
    }
    return false;
};

// Sincronizar cambios con el padre
watch(
    internalContacts,
    (newContacts) => {
        contactErrors.value = newContacts.map((contact) => validateContact(contact));
        if (hasDuplicateContact(newContacts)) {
            alertMessage.value = 'Ya existe un contacto con el mismo nombre, correo y teléfono.';
        } else if (
            alertMessage.value === 'Ya existe un contacto con el mismo nombre, correo y teléfono.'
        ) {
            alertMessage.value = '';
        }

        emit('update:additionalContacts', newContacts);
    },
    { deep: true },
);

// Validar que todos los campos de los contactos estén llenos
const areContactsValid = () => {
    return internalContacts.value.every(
        (contact) => contact.name.trim() && contact.email.trim() && contact.phone.trim()
    );
};

// Agregar un contacto adicional
const addContact = () => {
    // Primero: todos los campos llenos
    if (!areContactsValid()) {
        alertMessage.value = 'Por favor, complete todos los campos antes de agregar un nuevo contacto.';
        contactErrors.value = internalContacts.value.map((contact) => validateContact(contact));
        return;
    }

    // Segundo: que no haya duplicados
    if (hasDuplicateContact(internalContacts.value)) {
        alertMessage.value = 'Ya existe un contacto con el mismo nombre, correo y teléfono.';
        return;
    }
    internalContacts.value.push({ name: '', email: '', phone: '' });
    contactErrors.value.push(validateContact({ name: '', email: '', phone: '' }));
    alertMessage.value = ''; // limpiamos alerta si había
};

// Eliminar un contacto adicional y sus errores
const removeContact = (index: number) => {
    internalContacts.value.splice(index, 1);
    contactErrors.value.splice(index, 1);
};

const showDuplicateAlertFn = () => {
    alertMessage.value = 'Ya existe un contacto con el mismo nombre, correo y teléfono.';
};

defineExpose({
    showDuplicateAlert: showDuplicateAlertFn,
});
</script>

<template>
    <div class="mt-2 relative">
        <transition name="fade">
            <div v-if="alertMessage" class="absolute -top-26 left-1/2 z-20 w-full max-w-xl -translate-x-1/2">
                <AlertBox
                    type="error"
                    title="Error"
                    :message="alertMessage"
                    @close="alertMessage = ''"
                />
            </div>
        </transition>

        <div class="flex justify-end mb-2">
            <Button @click="addContact" variant="default" size="sm">
                <PlusCircle class="h-4 w-4" />Agregar contacto
            </Button>
        </div>

        <div v-for="(contact, index) in internalContacts" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField
                    v-model="contact.name"
                    label="Nombre del contacto"
                    placeholder="Nombre"
                    required
                    :error="contactErrors[index]?.name"
                />
                <InputField
                    v-model="contact.email"
                    label="Correo electrónico"
                    type="email"
                    placeholder="Correo electrónico"
                    required
                    :error="contactErrors[index]?.email"
                />
                <InputField
                    v-model="contact.phone"
                    label="Teléfono"
                    placeholder="Teléfono"
                    required
                    :error="contactErrors[index]?.phone"
                />
                <div class="text-right">
                    <DeleteButton @click="removeContact(index)">Eliminar contacto</DeleteButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Agregar estilos específicos si es necesario */
</style>
