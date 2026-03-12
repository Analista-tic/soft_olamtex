<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expense_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_number')->unique();

            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');

            $table->date('payment_date')->nullable()->index();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();
        });

        $rows = DB::table('purchase_payments')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_payments.purchase_invoice_id')
            ->whereNotNull('purchase_payments.voucher_number')
            ->groupBy('purchase_payments.voucher_number')
            ->selectRaw('purchase_payments.voucher_number AS voucher_number')
            ->selectRaw('MAX(purchase_documents.contact_id) AS contact_id')
            ->selectRaw('MIN(purchase_payments.payment_date) AS payment_date')
            ->selectRaw('MAX(purchase_payments.reference) AS reference')
            ->selectRaw('MAX(purchase_payments.notes) AS notes')
            ->selectRaw('MAX(purchase_payments.created_by_user_id) AS created_by_user_id')
            ->selectRaw('MIN(purchase_payments.created_at) AS created_at')
            ->selectRaw('MAX(purchase_payments.updated_at) AS updated_at')
            ->get()
            ->map(static fn ($row) => [
                'voucher_number' => (int) $row->voucher_number,
                'contact_id' => $row->contact_id ? (int) $row->contact_id : null,
                'payment_date' => $row->payment_date,
                'account_id' => null,
                'payment_method' => null,
                'reference' => $row->reference,
                'notes' => $row->notes,
                'created_by_user_id' => $row->created_by_user_id ? (int) $row->created_by_user_id : null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ])
            ->all();

        if (!empty($rows)) {
            DB::table('finance_expense_vouchers')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('finance_expense_vouchers', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::dropIfExists('finance_expense_vouchers');
    }
};

