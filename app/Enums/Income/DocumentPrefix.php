<?php

namespace App\Enums\Income;

enum DocumentPrefix: string
{
    case QUOTE = 'COT';
    case SALES_ORDER = 'PV';
    case SALES_INVOICE = 'FV';
    case CREDIT_NOTE = 'NC';
    case DEBIT_NOTE = 'ND';
    case CASH_RECEIPT = 'RC';
}
