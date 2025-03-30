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
            $table->foreignId('rol_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('state_id')->constrained('states')->onDelete('cascade');
            $table->foreignId('type_identification_id')->nullable()->constrained('type_identifications')->onDelete('set null');
            $table->string('email', length: 100)->unique();
            $table->string('image', 200)->nullable();
            $table->string('password', 255);
            $table->integer('points')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
