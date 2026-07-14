<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('comedians_id')->nullable();
            $table->string('title');
            $table->string('badge')->nullable();
            $table->date('event_date');
            $table->datetime('start_time')->nullable()->change();
            $table->datetime('end_time')->nullable()->change();
            $table->string('color', 50)->default('#00d4ff');
            $table->string('image')->nullable()->after('color');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};