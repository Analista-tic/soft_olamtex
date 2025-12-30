import { readFileSync, writeFileSync } from 'fs';
const file = 'resources/js/pages/Expenses/PurchaseInvoices/Index.vue';
const content = readFileSync(file, 'utf8');
const dialogStart = content.indexOf('            <DialogContent>');
if (dialogStart === -1) {
  throw new Error('dialog content not found');
}
const prefix = content.slice(0, dialogStart);
const tail =     </AppLayout>
    <ConfirmDialog
        v-model:open= isDeleteDialogOpen
        title=Confirmar eliminación
        description=¿Estás seguro de que deseas eliminar esta factura? Esta acción no se puede deshacer.
        confirm-text=Eliminar
        cancel-text=Cancelar
        :destructive=true
        @confirm=deleteInvoice
    />
</template>
;
writeFileSync(file, prefix + tail);
