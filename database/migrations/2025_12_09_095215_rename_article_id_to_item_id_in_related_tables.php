<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) inventory_adjustment_lines
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            // quitar FK antigua
            $table->dropForeign(['article_id']); // nombre por defecto: inventory_adjustment_lines_article_id_foreign
            // renombrar columna
            $table->renameColumn('article_id', 'item_id');
            // crear FK nueva
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();
        });

        // 2) purchase_invoice_items
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->renameColumn('article_id', 'item_id');
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();
        });

        // 3) purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->renameColumn('article_id', 'item_id');
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();
        });

        // 4) warehouse_article (pivot)
        Schema::table('warehouse_article', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->renameColumn('article_id', 'item_id');
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // IMPORTANTE: aquí depende de si en el down de la otra migración
        // vuelves a renombrar 'items' -> 'articles'.
        // Asumo que SÍ la devuelves a 'articles'.

        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->renameColumn('item_id', 'article_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->renameColumn('item_id', 'article_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->renameColumn('item_id', 'article_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();
        });

        Schema::table('warehouse_article', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->renameColumn('item_id', 'article_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();
        });
    }
};
