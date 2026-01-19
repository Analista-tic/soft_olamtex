from pathlib import Path
path = Path('resources/js/pages/Expenses/PurchaseInvoices/Index.vue')
text = path.read_text()
marker_start = '        <PurchaseInvoicePrintDialog'
marker_end = '    </AppLayout>'
start = text.index(marker_start)
end = text.index(marker_end, start)
end = end + len(marker_end)
end_template = text.index('</template>', end)
end = end_template + len('</template>')
new_block = '''        <PurchaseInvoicePrintDialog
            v-if= isPrintDialogOpen
            :isOpen=isPrintDialogOpen
            :invoice=invoiceToPrint
            @close=isPrintDialogOpen = false
        />
    </AppLayout>
    <ConfirmDialog
        v-model:open=isDeleteDialogOpen
        title=Confirmar eliminación
        description=¿Estás seguro de que deseas eliminar esta factura? Esta acción no se puede deshacer.
        confirmText=Eliminar
        cancelText=Cancelar
        @confirm=deleteInvoice
    />
</template>'''
new_text = text[:start] + new_block
path.write_text(new_text)
