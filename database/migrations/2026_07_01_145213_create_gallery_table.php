<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();

            // Stored path/URL to the uploaded image (e.g. "gallery/abc123.jpg")
            $table->string('image')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Custom timestamp column names per request (instead of
            // Laravel's default created_at/updated_at)
            $table->timestamp('date_created')->useCurrent();
            $table->timestamp('date_updated')->useCurrent()->useCurrentOnUpdate();

            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};