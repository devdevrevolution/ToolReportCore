<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composition_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_composition_id')
                ->constrained('report_compositions')
                ->cascadeOnDelete();
            $table->foreignId('pdf_template_id')
                ->constrained('pdf_templates')
                ->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_composition_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composition_pages');
    }
};
