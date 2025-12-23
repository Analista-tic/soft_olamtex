<?php

namespace App\Mail;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $pdf;

    public function __construct(PurchaseDocument $order, $pdf)
    {
        $this->order = $order;
        $this->pdf = $pdf;
    }

    public function build()
    {
        return $this->subject('Orden de Compra #' . $this->order->id)
            ->view('pdf.purchase_order') // Usar la plantilla PDF como cuerpo del correo
            ->with(['order' => $this->order])
            ->attachData($this->pdf, 'orden_compra_'.$this->order->id.'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
