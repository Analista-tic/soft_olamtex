<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropColorIdFromItemsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('items')) {
            return;
        }

        if (Schema::hasColumn('items', 'color_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('color_id');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('items')) {
            return;
        }

        if (!Schema::hasColumn('items', 'color_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('color_id')->nullable()->constrained('colors');
            });
        }
    }
}
