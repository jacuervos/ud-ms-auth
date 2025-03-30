<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Agregar nuevas columnas
            $table->timestamps(); // created_at y updated_at
            $table->softDeletes(); // deleted_at

            // Modificar columnas existentes
            // $table->string('name', 150)->change(); // Ejemplo para cambiar tamaño
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar columnas agregadas
            $table->dropColumn(['created_at', 'updated_at', 'deleted_at']);

            // Revertir cambios en columnas modificadas
            // $table->string('name', 100)->change(); // Ejemplo para revertir tamaño
        });
    }
};
