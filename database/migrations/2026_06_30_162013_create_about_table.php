<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_sections', function (Blueprint $table) {
            $table->id();

            // Small eyebrow label, e.g. "Our Story"
            $table->string('eyebrow')->nullable();

            // Main heading, e.g. "A Safe Space For All Colors"
            // Supports an optional highlighted/gradient portion stored separately
            // so the frontend can style it differently (e.g. the second line).
            $table->string('title');
            $table->string('title_highlight')->nullable();

            // Two paragraph blocks shown under the heading
            $table->text('description_primary')->nullable();
            $table->text('description_secondary')->nullable();

            // Left-side photo + floating badge content
            $table->string('image_url')->nullable();
            $table->string('image_caption_title')->nullable(); // e.g. "Since 2019"
            $table->string('image_caption_subtitle')->nullable(); // e.g. "TOMAS MORATO, QC"

            $table->string('badge_emoji')->nullable(); // e.g. 🏳️‍🌈
            $table->string('badge_title')->nullable(); // e.g. "PRIDE ALWAYS"
            $table->string('badge_subtitle')->nullable(); // e.g. "Quezon City"

            $table->string('stat_value')->nullable(); // e.g. "5,000+"
            $table->string('stat_label')->nullable(); // e.g. "Community Members"

            // Whether this section is shown on the public site
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_sections');
    }
};