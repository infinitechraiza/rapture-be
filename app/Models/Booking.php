<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;
    protected $fillable = ['full_name', 'user_id', 'comedians_id', 'email', 'phone', 'date', 'scheduled_at', 'status', 'amount', 'notes'];

    protected $casts = ['date' => 'datetime', 'scheduled_at' => 'datetime', 'amount' => 'decimal:2'];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class,  'booking_event', 'booking_id', 'event_id')
            ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }


    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())
            ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'in_progress');
    }

    // ── Accessors ────────────────────────────────────────────

    public function getIsUpcomingAttribute(): bool
    {
        return $this->scheduled_at->isFuture()
            && in_array($this->status, ['pending', 'confirmed']);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Allow cancellation only if the appointment is more than 24 hours away
     * and is still in a cancellable state.
     */
    public function getCanCancelAttribute(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'])
            && $this->scheduled_at->isFuture()
            && now()->diffInHours($this->scheduled_at, false) > 24;
    }
}