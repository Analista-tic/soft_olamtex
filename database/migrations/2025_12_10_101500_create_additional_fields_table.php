<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::create('additional_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('field_type')->default('text');
            $table->json('options')->nullable();
            $table->string('default_value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('status')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('additional_fields');
    }
};
