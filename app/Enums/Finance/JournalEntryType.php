<?php

namespace App\Enums\Finance;

enum JournalEntryType: string
{
    case STANDARD = 'standard';
    case EXPENSE_VOUCHER = 'expense_voucher';
    case CASH_RECEIPT = 'cash_receipt';
    case PURCHASE_INVOICE = 'purchase_invoice';
    case SALES_INVOICE = 'sales_invoice';
    case CREDIT_NOTE = 'credit_note';
    case DEBIT_NOTE = 'debit_note';
    case ADJUSTMENT = 'adjustment';
    case OPENING = 'opening';
    case CLOSING = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Estandar',
            self::EXPENSE_VOUCHER => 'Comprobante de egreso',
            self::CASH_RECEIPT => 'Recibo de caja',
            self::PURCHASE_INVOICE => 'Factura de compra',
            self::SALES_INVOICE => 'Factura de venta',
            self::CREDIT_NOTE => 'Nota crédito',
            self::DEBIT_NOTE => 'Nota débito',
            self::ADJUSTMENT => 'Ajuste',
            self::OPENING => 'Apertura',
            self::CLOSING => 'Cierre',
        };
    }
}
