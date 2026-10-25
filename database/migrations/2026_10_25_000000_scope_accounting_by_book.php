<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contabilidades (books): los datos contables pasan de pertenecer a un
 * usuario a pertenecer a una contabilidad con miembros. Cada usuario
 * existente recibe su contabilidad personal con todos sus datos actuales.
 */
return new class extends Migration
{
    /** Tabla => [índices/únicos de user_id a reemplazar por book_id, ¿lleva created_by?] */
    private const TABLES = [
        'accounts' => [['unique' => ['code'], 'index' => ['type']], false],
        'accounting_mappings' => [['index' => ['operation_type_id', 'is_active']], false],
        'journal_entries' => [['unique' => ['number'], 'index' => ['date']], true],
        'operation_types' => [['unique' => ['code']], false],
        'operation_executions' => [['index' => ['executed_at'], 'index2' => ['status']], true],
    ];

    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 20)->default('shared');
            $table->string('template', 20)->default('business');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->date('accounting_closed_until')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'kind']);
        });

        Schema::create('book_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->unsignedBigInteger('contribution_account_id')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'user_id']);
        });

        Schema::create('book_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['book_id', 'email']);
            $table->index('email');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_book_id')->nullable()->index();
        });

        foreach (self::TABLES as $name => [, $author]) {
            Schema::table($name, function (Blueprint $table) use ($author): void {
                $table->unsignedBigInteger('book_id')->nullable();
                if ($author) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
            });
        }

        // Contabilidad personal por usuario con sus datos actuales.
        $now = now();
        foreach (DB::table('users')->orderBy('id')->get(['id', 'accounting_closed_until']) as $user) {
            $bookId = DB::table('books')->insertGetId([
                'name' => 'Personal',
                'kind' => 'personal',
                'template' => 'business',
                'owner_id' => $user->id,
                'accounting_closed_until' => $user->accounting_closed_until,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('book_user')->insert([
                'book_id' => $bookId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('users')->where('id', $user->id)->update(['current_book_id' => $bookId]);

            foreach (self::TABLES as $name => [, $author]) {
                DB::table($name)->where('user_id', $user->id)->update(
                    ['book_id' => $bookId] + ($author ? ['created_by' => $user->id] : []),
                );
            }
        }

        foreach (self::TABLES as $name => [$indexes, $author]) {
            Schema::table($name, function (Blueprint $table) use ($indexes): void {
                $table->dropForeign(['user_id']);
                foreach ($indexes as $kind => $columns) {
                    $kind === 'unique'
                        ? $table->dropUnique(['user_id', ...$columns])
                        : $table->dropIndex(['user_id', ...$columns]);
                }
            });

            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('user_id');
            });

            Schema::table($name, function (Blueprint $table) use ($indexes, $author): void {
                $table->unsignedBigInteger('book_id')->nullable(false)->change();
                $table->foreign('book_id')->references('id')->on('books')->cascadeOnDelete();
                if ($author) {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                }
                foreach ($indexes as $kind => $columns) {
                    $kind === 'unique'
                        ? $table->unique(['book_id', ...$columns])
                        : $table->index(['book_id', ...$columns]);
                }
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('accounting_closed_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('accounting_closed_until')->nullable();
        });

        foreach (self::TABLES as $name => [$indexes, $author]) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable();
            });

            // Cada fila vuelve al dueño de su contabilidad.
            foreach (DB::table('books')->get(['id', 'owner_id']) as $book) {
                DB::table($name)->where('book_id', $book->id)->update(['user_id' => $book->owner_id]);
            }

            Schema::table($name, function (Blueprint $table) use ($indexes, $author): void {
                $table->dropForeign(['book_id']);
                if ($author) {
                    $table->dropForeign(['created_by']);
                }
                foreach ($indexes as $kind => $columns) {
                    $kind === 'unique'
                        ? $table->dropUnique(['book_id', ...$columns])
                        : $table->dropIndex(['book_id', ...$columns]);
                }
            });

            Schema::table($name, function (Blueprint $table) use ($author): void {
                $table->dropColumn($author ? ['book_id', 'created_by'] : ['book_id']);
            });

            Schema::table($name, function (Blueprint $table) use ($indexes): void {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                foreach ($indexes as $kind => $columns) {
                    $kind === 'unique'
                        ? $table->unique(['user_id', ...$columns])
                        : $table->index(['user_id', ...$columns]);
                }
            });
        }

        foreach (DB::table('books')->where('kind', 'personal')->get(['owner_id', 'accounting_closed_until']) as $book) {
            DB::table('users')->where('id', $book->owner_id)->update(['accounting_closed_until' => $book->accounting_closed_until]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['current_book_id']);
            $table->dropColumn('current_book_id');
        });

        Schema::dropIfExists('book_invitations');
        Schema::dropIfExists('book_user');
        Schema::dropIfExists('books');
    }
};
