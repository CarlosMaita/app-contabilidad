<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operation_type_id')->constrained()->restrictOnDelete();
            $table->json('payload');
            $table->date('executed_at'); // fecha contable
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | posted | failed | unmapped | voided
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'executed_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_executions');
    }
};
