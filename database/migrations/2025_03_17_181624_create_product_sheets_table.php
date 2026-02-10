<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_sheets', function (Blueprint $table) {
            $table->id();
            // IDs referenciados en parameters - sin foreign keys
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('world_id');
            $table->unsignedBigInteger('fabric_base_id');
            $table->unsignedBigInteger('yarn_base_id')->nullable();
            $table->enum('type', ['producto', 'muestra']);
            $table->float('billing_price')->nullable();
            $table->float('selling_price')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('woven_product');
            $table->string('description')->nullable();
            $table->string('flat_sketch_path')->nullable();
            $table->string('sample_reference')->nullable();
            $table->string('product_reference')->nullable();
            $table->json('processes');
            $table->unsignedBigInteger('operational_list_id')->nullable();
            $table->enum('status', ['complete', 'incomplete']);
            $table->softDeletes();
            $table->timestamps();
        });

        if (Schema::hasTable('operational_lists')) {
            Schema::table('product_sheets', function (Blueprint $table) {
                $table->foreign('operational_list_id')
                    ->references('id')
                    ->on('operational_lists')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        Schema::table('product_sheets', function (Blueprint $table) {
            $table->unique('product_reference', 'product_sheets_product_ref_unique');
            $table->unique('sample_reference', 'product_sheets_sample_ref_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_sheets')) {
            Schema::table('product_sheets', function (Blueprint $table) {
            $table->dropUnique('product_sheets_product_ref_unique');
            $table->dropUnique('product_sheets_sample_ref_unique');
            });

            if (Schema::hasTable('operational_lists')) {
                Schema::table('product_sheets', function (Blueprint $table) {
                    $table->dropForeign(['operational_list_id']);
                });
            }
        }

        Schema::dropIfExists('product_sheets');
    }
};
