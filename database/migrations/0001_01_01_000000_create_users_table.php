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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 15)->nullable();
            $table->string('identification', 50)->nullable();
            $table->unsignedInteger('type_identification_id')->nullable();
            $table->unsignedInteger('rol_id')->nullable();
            $table->string('email', 100)->unique();
            $table->unsignedInteger('state_id')->nullable();
            $table->string('image', 200)->nullable();
            $table->string('password', 255);
            $table->integer('points')->default(0)->nullable();
        });

        // Crear tabla password_reset_tokens
        /* Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        }); */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       // Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
