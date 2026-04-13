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
            $table->enum('contact_type', ['proveedor', 'cliente', 'cliente_proveedor']); // Tipo de contacto
            $table->enum('id_type', ['nit', 'cc', 'nit_otro_pais', 'pp', 'pep', 'die', 'ce', 'te', 'ti', 'rc', 'nuip', 'ppt']); // Tipo de identificacion
            $table->string('id_number')->unique(); // Numero de identificacion
            $table->string('dv')->nullable(); // DV
            $table->string('full_name'); // Nombre completo o razon social
            $table->enum('person_type', ['persona_natural', 'persona_juridica']); // Tipo de persona
            $table->json('groups')->nullable(); // Cambiar subgroups a groups
            $table->string('municipality')->nullable(); // Municipio
            $table->string('department')->nullable(); // Departamento
            $table->text('address')->nullable(); // Direccion
            $table->string('email')->unique(); // Correo electronico
            $table->string('phone')->nullable(); // Telefono
            $table->json('additional_contacts')->nullable(); // Contactos adicionales
            $table->json('branches')->nullable(); // Sucursales
            $table->timestamps();
            $table->softDeletes(); // Para manejar eliminaciones suaves
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
