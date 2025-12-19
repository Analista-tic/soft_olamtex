<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseArticleTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_article', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('article_id')->constrained();
            $table->integer('quantity')->default(0);
            $table->integer('stock_min')->default(0);
            $table->integer('stock_max')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'article_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_article');
    }
}
