<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->string('product_reference_2')->nullable();
            $table->json('processes');
            $table->unsignedBigInteger('operational_list_id')->nullable();
            $table->enum('status', ['complete', 'incomplete']);
            // Versionamiento
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // FK para versionamiento (auto-referencial)
            $table->foreign('parent_id')
                ->references('id')
                ->on('product_sheets')
                ->nullOnDelete();

            $table->index('parent_id', 'product_sheets_parent_id_index');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sheets');
    }
};
