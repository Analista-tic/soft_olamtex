<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por evento del sistema que puede avisar a alguien (catálogo en NotificationEventRegistry).
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('channel_database')->default(true); // campana
            $table->boolean('channel_mail')->default(false);
            $table->boolean('exclude_actor')->default(true); // no avisar a quien ejecuta la acción
            $table->timestamps();
        });

        // A quién se avisa: rol, área, usuario o destinatario automático (según el contexto del evento).
        Schema::create('notification_event_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->string('recipient_type', 20); // role | area | user | dynamic
            $table->string('recipient_value', 100);
            $table->string('permission_filter', 150)->nullable(); // solo quien además tenga este permiso
            $table->timestamps();

            $table->index(['notification_event_id', 'recipient_type'], 'notif_recipients_event_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_recipients');
        Schema::dropIfExists('notification_events');
    }
};
