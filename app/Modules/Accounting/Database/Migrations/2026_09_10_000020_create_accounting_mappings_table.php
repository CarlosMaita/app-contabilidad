<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operation_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('description_template', 500)->nullable();
            $table->timestamps();

            $table->unique(['operation_type_id', 'version']);
            $table->index(['user_id', 'operation_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_mappings');
    }
};
