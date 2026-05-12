<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A — Plan definitivo Mes/Año.
 *
 * Agrega las columnas canónicas de temporada y despacho a production_orders.
 * Los campos legacy season/dispatch_month se conservan durante la transición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            // Temporada canónica
            $table->tinyInteger('season_month')->unsigned()->nullable()->after('season')
                ->comment('Mes de temporada 1–12');
            $table->smallInteger('season_year')->unsigned()->nullable()->after('season_month')
                ->comment('Año de temporada ej. 2026');

            // Despacho canónico
            $table->tinyInteger('dispatch_month_num')->unsigned()->nullable()->after('dispatch_month')
                ->comment('Mes de despacho 1–12');
            $table->smallInteger('dispatch_year')->unsigned()->nullable()->after('dispatch_month_num')
                ->comment('Año de despacho ej. 2026');

            // Índices para filtros operativos frecuentes
            $table->index(['season_year', 'season_month'], 'po_season_year_month');
            $table->index(['dispatch_year', 'dispatch_month_num'], 'po_dispatch_year_month');
            $table->index(['status', 'season_year', 'season_month'], 'po_status_season_year_month');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropIndex('po_season_year_month');
            $table->dropIndex('po_dispatch_year_month');
            $table->dropIndex('po_status_season_year_month');
            $table->dropColumn(['season_month', 'season_year', 'dispatch_month_num', 'dispatch_year']);
        });
    }
};
