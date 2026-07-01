<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();

            // The user that was changed (target)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The admin/actor who made the change (nullable in case the
            // acting account is later deleted — keep the log row alive)
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // e.g. "status_updated", "role_updated", "profile_updated"
            $table->string('action');

            // Free-form human readable summary, e.g. "Status changed from
            // pending to approved"
            $table->string('description')->nullable();

            // Field-level diff, e.g. { "status": { "old": "pending", "new": "approved" } }
            $table->json('changes')->nullable();

            $table->string('ip_address')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};