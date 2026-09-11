<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number'); // correlativo por usuario
            $table->date('date');
            $table->string('description', 500);
            $table->foreignId('operation_execution_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('accounting_mapping_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('status', 20)->default('posted'); // posted | reversed
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
