<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_vars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_template_id')
                ->constrained('pdf_templates')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('value')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->boolean('is_required')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['pdf_template_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_vars');
    }
};
