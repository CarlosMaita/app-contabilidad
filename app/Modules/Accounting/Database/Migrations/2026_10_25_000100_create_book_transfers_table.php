<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferencias entre contabilidades: enlazan el asiento de salida (en la
 * contabilidad de origen) con el de entrada (en la de destino).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('to_book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('from_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('to_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('date');
            $table->string('description', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_transfers');
    }
};
