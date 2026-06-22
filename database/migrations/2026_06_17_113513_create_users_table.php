<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
        public function up(): void
        {
        Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('phone');
        $table->string('profile_url')->nullable();
        $table->string('password');

        // Custom reset + verification tokens
        $table->string('reset_token', 255)->nullable();
        $table->timestamp('reset_token_expires_at')->nullable();
        $table->string('verification_token')->nullable();
        $table->timestamp('verification_token_expires_at')->nullable();

        $table->enum('user_role', ['user', 'admin'])->default('user');
        $table->enum('status', ['pending', 'approved'])->default('pending');
        $table->timestamp('email_verified_at')->nullable();

        $table->rememberToken(); // adds remember_token VARCHAR(100) nullable
        $table->timestamps();    // adds created_at & updated_at
    });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
