<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnavailableDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'unavailable_date',
        'reason',
        'type',
        'event_booking_id',
        'is_override_allowed'
    ];

    protected $casts = [
        'unavailable_date' => 'date',
        'is_override_allowed' => 'boolean',
    ];

    /**
     * Relationship: Belongs to a venue
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Relationship: Belongs to an event booking (optional)
     */
    public function eventBooking()
    {
        return $this->belongsTo(EventBooking::class);
    }

    /**
     * Scope: Filter by venue
     */
    public function scopeForVenue($query, $venueId)
    {
        return $query->where(function($q) use ($venueId) {
            $q->where('venue_id', $venueId)
              ->orWhereNull('venue_id'); // Include global dates
        });
    }

    /**
     * Scope: Filter by type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Booked dates
     */
    public function scopeBooked($query)
    {
        return $query->where('type', 'booked');
    }

    /**
     * Scope: Blocked dates
     */
    public function scopeBlocked($query)
    {
        return $query->where('type', 'blocked');
    }
}