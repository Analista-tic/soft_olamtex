<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_documents', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificación general
            $table->string('document_number', 50)->nullable()->index(); // SOL-2025-001, ORD-2025-001, FAC-2025-001
            $table->enum('document_type', ['purchase_request', 'purchase_order', 'purchase_invoice'])->index();

            // Encadenamiento (Solicitud -> Orden -> Factura)
            $table->unsignedBigInteger('parent_document_id')->nullable()->index();
            $table->foreign('parent_document_id')
                ->references('id')
                ->on('purchase_documents')
                ->onDelete('set null');

            // Proveedor / contacto
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
            // Campos para proveedor no registrado
            $table->string('provider_name')->nullable();
            $table->string('provider_phone')->nullable();

            // Fechas principales
            $table->date('date')->nullable();           // Fecha del documento
            $table->date('delivery_date')->nullable(); // Fecha de entrega (si aplica)

            // Razon del documento (diferencias, observaciones o comentarios adicionales)
            $table->text('reason')->nullable();

            // Descripción general
            $table->text('description')->nullable();

            // Usuarios involucrados
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unsignedBigInteger('executed_by_user_id')->nullable()->index();
            $table->foreign('executed_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Estados generales
            // Ej:
            //  - Requests: pending | approved | rejected
            //  - Orders:   pending | sent
            //  - Invoices: pending | in_process | approved | rejected
            $table->enum('status', ['pending', 'in_process', 'approved', 'rejected'])->default('pending')->index();

            // Estado de aprobación más "funcional" (sobre todo para factura)
            $table->enum('approval_status', ['pending', 'in_process', 'approved', 'rejected'])->nullable()->index();

            // Datos específicos por tipo
            $table->string('supplier_order_number', 50)->nullable();   // Orden proveedor
            $table->string('supplier_invoice_number', 50)->nullable(); // Factura proveedor

            // Retenciones de la factura (si decides consolidarlas en JSON)
            $table->json('withholdings')->nullable();

            // Campos de discrepancia / rechazo (especialmente para facturas)
            $table->text('rejection_reason')->nullable();          // Motivo de discrepancia / rechazo
            $table->string('rejection_evidence_image', 255)->nullable(); // Path imagen evidencia
            $table->text('rejection_notes')->nullable();           // Notas adicionales

            $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index();
            $table->foreign('rejected_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamp('rejected_at')->nullable();

            // Trazabilidad temporal del ciclo
            $table->timestamp('requested_at')->nullable(); // CuÃ¡ndo se solicitÃ³
            $table->timestamp('ordered_at')->nullable();   // CuÃ¡ndo se ordenÃ³
            $table->timestamp('invoiced_at')->nullable();  // CuÃ¡ndo se facturÃ³
            $table->timestamp('approved_at')->nullable();  // CuÃ¡ndo se aprobÃ³ (si aplica)

            // AuditorÃ­a base
            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->index(['document_type', 'status']);
            $table->index(['document_type', 'approval_status']);
            $table->index(['contact_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropForeign(['parent_document_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['executed_by_user_id']);
            $table->dropForeign(['rejected_by_user_id']);
        });

        Schema::dropIfExists('purchase_documents');
    }
};



