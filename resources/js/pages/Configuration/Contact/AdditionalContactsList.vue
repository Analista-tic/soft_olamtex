<script setup lang="ts">
import { PlusCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

// Components
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';

interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}

const props = defineProps<{
    additionalContacts: AdditionalContact[];
}>();

const emit = defineEmits<{
    (e: 'update:additionalContacts', updatedContacts: AdditionalContact[]): void;
}>();

// Estado del diálogo y contacto en edición
const isDialogOpen = ref(false);
const editingContact = ref<AdditionalContact>({ name: '', phone: '', email: '' });
const editingContactIndex = ref<number | null>(null);

// Métodos para manejar contactos
const addContact = () => {
    editingContact.value = { name: '', phone: '', email: '' };
    editingContactIndex.value = null;
    isDialogOpen.value = true;
};

const editContact = (index: number) => {
    editingContact.value = { ...props.additionalContacts[index] };
    editingContactIndex.value = index;
    isDialogOpen.value = true;
};

const saveContact = () => {
    if (!editingContact.value.name.trim()) {
        return; // Validación básica
    }

    const updatedContacts = [...props.additionalContacts];

    if (editingContactIndex.value === null) {
        // Añadir nuevo contacto
        updatedContacts.push({ ...editingContact.value });
    } else {
        // Editar contacto existente
        updatedContacts[editingContactIndex.value] = { ...editingContact.value };
    }

    emit('update:additionalContacts', updatedContacts);
    closeDialog();
};

const removeContact = (index: number) => {
    const updatedContacts = props.additionalContacts.filter((_, i) => i !== index);
    emit('update:additionalContacts', updatedContacts);
};

const closeDialog = () => {
    isDialogOpen.value = false;
};

// Resetear el formulario cuando se cierra el diálogo
watch(isDialogOpen, (isOpen) => {
    if (!isOpen) {
        editingContact.value = { name: '', phone: '', email: '' };
        editingContactIndex.value = null;
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Contactos Adicionales</h3>
            <Button @click="addContact" variant="default" size="sm">
                <PlusCircle class="mr-2 h-4 w-4" />
                Nuevo Contacto
            </Button>
        </div>

        <div v-if="additionalContacts.length" class="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[30%]">Nombre</TableHead>
                        <TableHead class="w-[30%]">Teléfono</TableHead>
                        <TableHead class="w-[30%]">Correo</TableHead>
                        <TableHead class="w-[10%] text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(contact, index) in additionalContacts" :key="index">
                        <TableCell>{{ contact.name }}</TableCell>
                        <TableCell>{{ contact.phone }}</TableCell>
                        <TableCell>{{ contact.email }}</TableCell>
                        <TableCell class="flex justify-end space-x-2">
                            <EditButton @click="editContact(index)" />
                            <DeleteButton @click="removeContact(index)" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-else class="rounded-lg border py-8 text-center text-gray-500">No hay contactos adicionales registrados.</div>

        <Dialog v-model:open="isDialogOpen" :close-on-outside-click="false">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ editingContactIndex === null ? 'Añadir Contacto' : 'Editar Contacto' }}
                    </DialogTitle>
                    <DialogDescription>
                        Completa los campos para {{ editingContactIndex === null ? 'añadir' : 'editar' }} un contacto adicional.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <InputField v-model="editingContact.name" label="Nombre" placeholder="Nombre completo" required />
                    <InputField v-model="editingContact.phone" label="Teléfono" placeholder="Número de teléfono" type="tel" />
                    <InputField v-model="editingContact.email" label="Correo" placeholder="Correo electrónico" type="email" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeDialog"> Cancelar </Button>
                    <Button type="button" @click="saveContact"> Guardar </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
