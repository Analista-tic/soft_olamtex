<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticlesTable extends Migration
{
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('category_sub_group_id')->constrained();
            $table->foreignId('color_id')->constrained();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('img')->nullable();
            $table->decimal('impuesto', 8, 2)->default(0);
            $table->integer('stock_min')->default(0);
            $table->integer('stock_max')->default(0);
            $table->string('bar_code')->nullable();
            $table->boolean('negative_movement')->default(false);
            $table->string('description');
            $table->json('extra_fields')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
