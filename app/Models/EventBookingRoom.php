<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBookingRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_booking_id',
        'room_id',
        'room_name',
        'bed_type',
        'capacity',
        'size',
        'quantity',
        'price_per_night',
        'number_of_nights',
        'subtotal',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'quantity' => 'integer',
        'price_per_night' => 'decimal:2',
        'number_of_nights' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Get the event booking that owns this room
     */
    public function eventBooking()
    {
        return $this->belongsTo(EventBooking::class);
    }

    /**
     * Calculate subtotal automatically
     */
    public function calculateSubtotal(): float
    {
        return $this->price_per_night * $this->quantity * $this->number_of_nights;
    }
}