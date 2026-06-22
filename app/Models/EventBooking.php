<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'venue_id',
        'check_in_date',
        'check_out_date',
        'is_single_day',
        'number_of_days',
        'number_of_nights',
        'expected_attendees',
        'needs_accommodation',
        'organization',
        'event_name',
        'contact_person',
        'position',
        'email',
        'phone',
        'details',
        'venue_price_per_day',
        'venue_total',
        'rooms_total',
        'grand_total',
        'status',
        'admin_notes',
        'confirmed_at',
        'rejected_at',
        'completed_at',
    ];

    protected $casts = [
        'check_in_date'      => 'date',
        'check_out_date'     => 'date',
        'is_single_day'      => 'boolean',
        'needs_accommodation' => 'boolean',
        'venue_price_per_day' => 'decimal:2',
        'venue_total'        => 'decimal:2',
        'rooms_total'        => 'decimal:2',
        'grand_total'        => 'decimal:2',
        'confirmed_at'       => 'datetime',
        'rejected_at'        => 'datetime',
        'completed_at'       => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    // ── Relationships ──────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function rooms()
    {
        return $this->hasMany(EventBookingRoom::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // ── Status helpers ────────────────────────────────────────

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isConfirmed(): bool  { return $this->status === self::STATUS_CONFIRMED; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }
    public function isRejected(): bool   { return $this->status === self::STATUS_REJECTED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }

    // ── Computed ──────────────────────────────────────────────

    public function getReferenceNumberAttribute(): string
    {
        return 'EVB-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}