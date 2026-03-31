<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_data_sheet_supplies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id')->index();
            $table->string('uid', 100)->index()->comment('UUID frontend para correlación sin recargar');
            $table->string('name', 200);
            $table->string('stage', 30)->default('GENERAL')->comment('GENERAL|CORTE|CONFECCION|TERMINACION|OTRO');
            $table->unsignedSmallInteger('sequence')->nullable();
            $table->string('display_label', 100)->nullable();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('requested_name', 200)->nullable();
            $table->string('supplier', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('moda_linea', 10)->nullable()->default('NA')->comment('MODA|LINEA|NA');
            $table->string('uom', 60)->nullable();
            $table->decimal('qty_per_unit', 14, 6)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->unsignedBigInteger('internal_request_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->string('document_url', 2000)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('production_order_id')
                ->references('id')->on('production_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_data_sheet_supplies');
    }
};
