<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            // Subcuenta auxiliar (ej. "CxC de Luis"): recibe imputaciones
            // pero no aparece en mayor ni estados; su padre consolida.
            $table->boolean('is_auxiliary')->default(false)->after('is_postable');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('is_auxiliary');
        });
    }
};
