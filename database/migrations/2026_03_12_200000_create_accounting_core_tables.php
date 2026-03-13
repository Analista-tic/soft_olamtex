<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('account_type', 30);

            // Account hierarchy
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('set null');
            $table->unsignedTinyInteger('level')->default(1);

            // Behavior flags
            $table->boolean('allows_entries')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('special_type', 50)->nullable();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'is_active']);
            $table->index(['allows_entries', 'is_active']);
            $table->index(['special_type', 'is_active']);
            $table->index(['parent_id', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entry_number')->unique();
            $table->date('entry_date');
            $table->string('entry_type', 50)->default('standard');
            $table->string('status', 20)->default('posted');
            $table->timestamp('posted_at')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('reversal_reason', 255)->nullable();

            // Source document traceability
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Reversal links
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable();
            $table->foreign('reversed_by_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->onDelete('set null');
            $table->unsignedBigInteger('reverses_entry_id')->nullable();
            $table->foreign('reverses_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->onDelete('set null');

            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['entry_date', 'status']);
            $table->index(['status', 'posted_at']);
            $table->index(['entry_type', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index('created_by_user_id');
            $table->index('reversed_by_entry_id');
            $table->index('reverses_entry_id');
            $table->index('posted_at');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('journal_entry_id');
            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->onDelete('cascade');

            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('restrict');

            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');

            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->string('line_reference', 100)->nullable();

            $table->timestamps();

            $table->index(['journal_entry_id', 'account_id']);
            $table->index(['account_id', 'contact_id']);
            $table->index('contact_id');
        });

        // Protect accounting lines:
        // - no debit and credit positive at the same time
        // - no both zero
        // - no negatives
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE journal_entry_lines
                ADD CONSTRAINT chk_jel_debit_credit_one_side_positive
                CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))'
            );
        }

        DB::table('finance_document_sequences')->updateOrInsert(
            ['document_type' => 'journal_entry'],
            [
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_accounts');

        DB::table('finance_document_sequences')
            ->where('document_type', 'journal_entry')
            ->delete();
    }
};
