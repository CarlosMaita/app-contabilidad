<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('side', 6); // debit | credit
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('account_variable', 60)->nullable(); // variable de tipo account
            $table->string('amount_expression', 255);
            $table->string('memo_template', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_lines');
    }
};
