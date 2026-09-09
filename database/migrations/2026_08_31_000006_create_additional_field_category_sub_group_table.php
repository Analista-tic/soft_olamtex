<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pivote de asociación entre campos adicionales y subgrupos de categoría.
// La FK apunta a settings.id (no a una tabla "additional_fields" dedicada) porque
// los campos adicionales son un diseño EAV heredado: se guardan en settings con kind='field'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_field_category_sub_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('setting_id');
            $table->unsignedBigInteger('category_sub_group_id');

            $table->foreign('setting_id', 'afcsg_setting_id_foreign')
                ->references('id')->on('settings')->cascadeOnDelete();
            $table->foreign('category_sub_group_id', 'afcsg_sub_group_id_foreign')
                ->references('id')->on('category_sub_groups')->cascadeOnDelete();

            $table->unique(['setting_id', 'category_sub_group_id'], 'afcsg_setting_sub_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_field_category_sub_group');
    }
};
