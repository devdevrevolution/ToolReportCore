<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->migrateSqlite();
        } else {
            $this->migrateStandard();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rollbackSqlite();
        } else {
            $this->rollbackStandard();
        }
    }

    private function migrateStandard(): void
    {
        // Add the new FK column
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->foreignId('report_composition_id')
                ->nullable()
                ->constrained('report_compositions')
                ->onDelete('set null');
        });

        // Drop NOT NULL on pdf_template_id
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('pdf_template_id')->nullable()->change();
        });
    }

    private function rollbackStandard(): void
    {
        // Restore NOT NULL and drop the FK column
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('pdf_template_id')->nullable(false)->change();
        });

        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->dropForeign(['report_composition_id']);
            $table->dropColumn('report_composition_id');
        });
    }

    private function migrateSqlite(): void
    {
        $from = 'pdf_documents';
        $temp = 'pdf_documents_temp';

        Schema::disableForeignKeyConstraints();

        // Create temp table with the new schema
        Schema::create($temp, function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_template_id')
                ->nullable()
                ->constrained('pdf_templates')
                ->cascadeOnDelete();
            $table->foreignId('report_composition_id')
                ->nullable()
                ->constrained('report_compositions')
                ->onDelete('set null');
            $table->string('title');
            $table->json('data')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Copy all existing data — report_composition_id defaults to NULL
        $columns = ['id', 'pdf_template_id', 'title', 'data', 'file_path', 'file_size', 'status', 'error_message', 'generated_at', 'created_at', 'updated_at'];
        $columnList = implode(', ', $columns);

        DB::statement("INSERT INTO {$temp} ({$columnList}) SELECT {$columnList} FROM {$from}");

        // Swap tables
        Schema::drop($from);
        Schema::rename($temp, $from);

        Schema::enableForeignKeyConstraints();
    }

    private function rollbackSqlite(): void
    {
        $from = 'pdf_documents';
        $temp = 'pdf_documents_temp';

        Schema::disableForeignKeyConstraints();

        Schema::create($temp, function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_template_id')
                ->constrained('pdf_templates')
                ->cascadeOnDelete();
            $table->string('title');
            $table->json('data')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Copy data, excluding the report_composition_id column
        $columns = ['id', 'pdf_template_id', 'title', 'data', 'file_path', 'file_size', 'status', 'error_message', 'generated_at', 'created_at', 'updated_at'];
        $columnList = implode(', ', $columns);

        DB::statement("INSERT INTO {$temp} ({$columnList}) SELECT {$columnList} FROM {$from}");

        Schema::drop($from);
        Schema::rename($temp, $from);

        Schema::enableForeignKeyConstraints();
    }
};
