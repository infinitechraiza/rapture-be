<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_values', function (Blueprint $table) {
            $table->id();

            // Links each value card to its parent "Our Story" section
            $table->foreignId('about_section_id')
                ->nullable()
                ->constrained('about_sections')
                ->cascadeOnDelete();

            // lucide-react icon name, e.g. "ShieldCheck", "Mic2", "Coffee", "Heart"
            $table->string('icon');

            // Card content
            $table->string('title');
            $table->text('description')->nullable();

            // Controls card order in the grid
            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['about_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_values');
    }
};