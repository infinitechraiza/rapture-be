<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    public const DEFAULT_COLOR = '#00d4ff';

    protected $fillable = [
        'user_id',
        'comedians_id',
        'title',
        'event_date',
        'start_time',
        'end_time',
        'color',
        'description',
    ];

    /**
     * IMPORTANT: event_date is intentionally NOT cast to 'date' or 'datetime'.
     *
     * Casting it as a date causes Laravel/Carbon to attach a timezone-aware
     * Carbon instance. When that gets serialized to JSON it turns into a
     * full ISO-8601 string like "2026-06-22T00:00:00.000000Z". If anything
     * downstream (browser, PHP, frontend Date()) reads that UTC midnight
     * timestamp in a timezone behind UTC, it rolls back to the previous day
     * — which is exactly the "yesterday" bug you were seeing.
     *
     * Keeping it as a plain string means whatever you send in ("2026-06-22")
     * is exactly what gets stored and returned — no timezone conversion,
     * no off-by-one.
     */
    protected $casts = [
        // 'event_date' => 'date', // ← removed on purpose, see note above
    ];

    public function comedians(): BelongsToMany
    {
        return $this->belongsToMany(
            Comedian::class,
            'event_comedian',
            'event_id',
            'comedian_id'
        )->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    /**
     * Scope: events on a given date whose time range overlaps the given
     * start/end times. Standard interval-overlap check:
     * overlap exists if (startA < endB) AND (endA > startB).
     *
     * Optionally exclude a specific event id (used during update, so the
     * event being edited doesn't collide with itself).
     */
    public function scopeOverlapping($query, string $date, string $startTime, string $endTime, ?int $excludeId = null)
    {
        $query->where('event_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }


    
    public function setBookingEvent($query, $year, $month)
    {
        return $query->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
    }

}