<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replace the bookings migration with this:
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->dateTime('date');
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending','approved','confirmed','completed','cancelled'])->default('pending');
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pivot: one booking → many events
        Schema::create('booking_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_event');
        Schema::dropIfExists('bookings');
    }
};