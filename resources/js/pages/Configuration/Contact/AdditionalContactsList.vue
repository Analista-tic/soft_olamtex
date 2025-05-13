<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import Button from '@/components/ui/button/Button.vue';
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

// Sincronizar cambios con el padre
watch(
    internalContacts,
    (newContacts) => {
        contactErrors.value = newContacts.map((contact) => validateContact(contact));
        emit('update:additionalContacts', newContacts);
    },
    { deep: true },
);

// Validar que todos los campos de los contactos estén llenos
const areContactsValid = () => {
    return internalContacts.value.every((contact) => contact.name.trim() && contact.email.trim() && contact.phone.trim());
};

// Modificar la función para agregar un contacto adicional solo si los campos actuales son válidos
const addContact = () => {
    if (areContactsValid()) {
        internalContacts.value.push({ name: '', email: '', phone: '' });
        contactErrors.value.push(validateContact({ name: '', email: '', phone: '' }));
    } else {
        alert('Por favor, complete todos los campos antes de agregar un nuevo contacto.');
        contactErrors.value = internalContacts.value.map((contact) => validateContact(contact));
    }
};

// Eliminar un contacto adicional y sus errores
const removeContact = (index: number) => {
    internalContacts.value.splice(index, 1);
    contactErrors.value.splice(index, 1);
};
</script>

<template>
    <div class="mt-2">
        <div class="flex justify-end">
            <Button @click="addContact" variant="default" size="sm"><PlusCircle class="h-4 w-4" />Agregar contacto</Button>
        </div>
        <div v-for="(contact, index) in internalContacts" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField v-model="contact.name" label="Nombre del contacto" placeholder="Nombre" required :error="contactErrors[index]?.name" />
                <InputField
                    v-model="contact.email"
                    label="Correo electrónico"
                    type="email"
                    placeholder="Correo electrónico"
                    required
                    :error="contactErrors[index]?.email"
                />
                <InputField v-model="contact.phone" label="Teléfono" placeholder="Teléfono" required :error="contactErrors[index]?.phone" />
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
