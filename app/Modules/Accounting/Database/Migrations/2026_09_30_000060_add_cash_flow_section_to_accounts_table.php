<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            // Actividad del flujo de efectivo de una cuenta de balance.
            // Null en cuentas de resultado; si falta, se hereda del padre.
            $table->string('cash_flow_section', 30)->nullable()->after('pnl_section');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('cash_flow_section');
        });
    }
};
