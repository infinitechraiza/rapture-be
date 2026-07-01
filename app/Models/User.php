<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    // ── Fillable ──────────────────────────────────────────────
    // Matches every column in the users migration exactly.
    // verification_token / reset_token are removed — password
    // resets now go through the standard password_reset_tokens table.
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'profile_url',
        'password',
        'user_role',
        'status',
        'email_verified_at',
        'verification_token',              // ← add
        'verification_token_expires_at',   // ← add
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_token_expires_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->user_role === 'admin';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    // ── Relationships ─────────────────────────────────────────

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function upcomingBookings(): HasMany
    {
        return $this->hasMany(Booking::class)
            ->upcoming()
            ->orderBy('check_in');
    }

    public function activeBookings(): HasMany
    {
        return $this->hasMany(Booking::class)
            ->active();
    }

    /**
     * Activity log entries where this user was the one changed (target).
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'user_id')
            ->latest();
    }

    /**
     * Activity log entries where this user was the one who made the
     * change (actor) — useful for an admin's own audit trail.
     */
    public function performedActivityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'actor_id')
            ->latest();
    }



    public function __construct(){
        parent::__construct();
    }

    public function build()
    {
        return $this->subject('Your account is pending approval')
            ->view('emails.account-pending-approval');
    }
}