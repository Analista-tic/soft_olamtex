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
            $table->foreignId('tax_id')->constrained();
            $table->string('bar_code')->nullable()->unique();
            $table->boolean('negative_movement')->default(false);
            $table->string('description');
            $table->json('extra_fields')->nullable();
            $table->string('unit_measure')->nullable();
            $table->boolean('status')->default(true);
            $table->string('item_type')->default('product');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('items');
    }
}
