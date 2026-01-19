import { readFileSync, writeFileSync } from 'fs';
const path = 'resources/js/pages/Expenses/PurchaseInvoices/Index.vue';
let text = readFileSync(path, 'utf8');
const oldBlock =         <PurchaseInvoicePrintDialog
            v-if= isPrintDialogOpen
            :isOpen=isPrintDialogOpen
            :invoice=invoiceToPrint
            @close=isPrintDialogOpen = false
        />
        <Dialog v-if=isDeleteDialogOpen v-model:open=isDeleteDialogOpen>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminaci?n</DialogTitle>
                    <DialogDescription>
                        ?Est?s seguro de que deseas eliminar esta factura? Esta acci?n no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant=outline @click=isDeleteDialogOpen = false>Cancelar</Button>
                    <Button variant=destructive @click=deleteInvoice>Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
;
const newBlock =         <PurchaseInvoicePrintDialog
            v-if=isPrintDialogOpen
            :isOpen=isPrintDialogOpen
            :invoice=invoiceToPrint
            @close=isPrintDialogOpen = false
        />
    </AppLayout>
    <ConfirmDialog
        v-model:open=isDeleteDialogOpen
        title=Confirmar eliminación
        description=¿Estás seguro de que deseas eliminar esta factura? Esta acción no se puede deshacer.
        confirm-text=Eliminar
        cancel-text=Cancelar
        :destructive=true
        @confirm=deleteInvoice
    />
</template>
;
if (!text.includes(oldBlock)) {
  throw new Error('old block not found');
}
text = text.replace(oldBlock, newBlock);
writeFileSync(path, text);
