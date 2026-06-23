<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('comedian_ids')->nullable()->constrained('comedians')->nullOnDelete();

            $table->dateTime('date'); // appointment date
            $table->dateTime('scheduled_at'); // appointment time

            $table->enum('status', [
                'pending',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('pending');

            $table->decimal('amount', 10, 2)->nullable(); // bar tab or reservation fee
            $table->text('notes')->nullable(); // special requests, table preference, etc.

            // This automatically adds created_at and updated_at
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};