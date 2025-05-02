<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type'); // Tipo de contacto
            $table->string('id_type'); // Tipo de identificación
            $table->string('id_number')->unique(); // Número de identificación
            $table->string('dv')->nullable(); // DV
            $table->string('full_name'); // Nombre completo o razón social
            $table->string('person_type'); // Tipo de persona
            $table->string('category'); // Categoría proveedor/cliente
            $table->string('municipality')->nullable(); // Municipio
            $table->string('department')->nullable(); // Departamento
            $table->text('address')->nullable(); // Dirección
            $table->string('email')->unique(); // Correo electrónico
            $table->string('phone')->nullable(); // Teléfono
            $table->json('additional_contacts')->nullable(); // Contactos adicionales
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
