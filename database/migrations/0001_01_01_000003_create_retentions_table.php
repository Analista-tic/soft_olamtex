<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRetentionsTable extends Migration
{
    public function up()
    {
        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percent', 5, 2);
            $table->string('type');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('retentions');
    }
}
