<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            // Sección del estado de resultados a la que aporta la cuenta.
            // Solo aplica a cuentas de tipo income/expense; null en el resto.
            $table->string('pnl_section', 30)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('pnl_section');
        });
    }
};
