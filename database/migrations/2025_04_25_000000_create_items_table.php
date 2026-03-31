<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_sub_group_id')->constrained();
            $table->string('name');
            $table->string('reference')->nullable();
            $table->string('img')->nullable();
            $table->foreignId('tax_id')->nullable()->index();
            $table->string('bar_code')->nullable()->unique();
            $table->boolean('negative_movement')->default(false);
            $table->string('description');
            $table->json('extra_fields')->nullable();
            $table->string('unit_measure')->nullable();
            $table->boolean('status')->default(true);
            $table->string('item_type')->default('product');
            $table->boolean('tracks_inventory')->default(true);
            $table->unsignedBigInteger('income_account_id')->nullable();
            $table->unsignedBigInteger('inventory_account_id')->nullable();
            $table->unsignedBigInteger('cost_of_sale_account_id')->nullable();
            $table->string('tariff_heading')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('costing_method', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('items');
    }
}
