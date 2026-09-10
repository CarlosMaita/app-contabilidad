<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_variables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60); // snake_case, clave dentro del payload
            $table->string('label', 120);
            $table->string('type', 20); // decimal | integer | string | date | boolean | account
            $table->boolean('is_required')->default(true);
            $table->string('default_value', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['operation_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_variables');
    }
};
