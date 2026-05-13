<?php

namespace App\Mail;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $pdf;

    public function __construct(PurchaseDocument $invoice, $pdf)
    {
        $this->invoice = $invoice;
        $this->pdf = $pdf;
    }

    public function build()
    {
        return $this->subject('Factura de Compra #' . $this->invoice->document_number)
            ->view('pdf.purchase_invoice')
            ->with(['invoice' => $this->invoice])
            ->attachData($this->pdf, 'factura_compra_' . $this->invoice->document_number . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
