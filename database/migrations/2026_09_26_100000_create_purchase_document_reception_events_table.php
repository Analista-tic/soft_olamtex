<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_document_reception_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('purchase_document_id');
            $table->foreign('purchase_document_id', 'pd_reception_events_doc_fk')
                ->references('id')
                ->on('purchase_documents')
                ->onDelete('cascade');

            // rejected | reopened
            $table->string('event_type', 30);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'pd_reception_events_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['purchase_document_id', 'occurred_at'], 'pd_reception_events_doc_date_idx');
        });

        // Los rechazos anteriores que ya guardaban fecha pasan a ser el primer evento de su historial.
        $rejected = DB::table('purchase_documents')
            ->where('document_type', 'purchase_invoice')
            ->where('status', 'rejected')
            ->whereNotNull('rejected_at')
            ->get(['id', 'rejection_reason', 'rejection_notes', 'rejected_by_user_id', 'rejected_at']);

        foreach ($rejected as $document) {
            DB::table('purchase_document_reception_events')->insert([
                'purchase_document_id' => $document->id,
                'event_type' => 'rejected',
                'reason' => $document->rejection_reason,
                'notes' => $document->rejection_notes,
                'user_id' => $document->rejected_by_user_id,
                'occurred_at' => $document->rejected_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_document_reception_events');
    }
};
