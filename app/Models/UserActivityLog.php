<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    use HasFactory;

    // ── Common action types ─────────────────────────────────
    // Centralized here so the controller and any future code can
    // reference these instead of typing raw strings.
    public const ACTION_STATUS_UPDATED = 'status_updated';
    public const ACTION_ROLE_UPDATED = 'role_updated';
    public const ACTION_PROFILE_UPDATED = 'profile_updated';
    public const ACTION_CREATED = 'created';
    public const ACTION_DELETED = 'deleted';

    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'description',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    /**
     * The user this log entry is about (the target of the change).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The admin/user who performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ── Convenience factory ──────────────────────────────────

    /**
     * Quick helper to record a log entry without repeating
     * boilerplate in controllers.
     *
     * UserActivityLog::record($user->id, $actorId, 'status_updated', [
     *     'status' => ['old' => 'pending', 'new' => 'approved'],
     * ]);
     */
    public static function record(
        int $userId,
        ?int $actorId,
        string $action,
        array $changes = [],
        ?string $description = null,
        ?string $ipAddress = null,
    ): self {
        return self::create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'action' => $action,
            'description' => $description ?? self::describe($action, $changes),
            'changes' => $changes,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Build a readable description from an action + changes array.
     * Override or extend this if you add more action types.
     */
    protected static function describe(string $action, array $changes): string
    {
        if (empty($changes)) {
            return match ($action) {
                self::ACTION_CREATED => 'User created.',
                self::ACTION_DELETED => 'User deleted.',
                default => str_replace('_', ' ', ucfirst($action)),
            };
        }

        $parts = [];
        foreach ($changes as $field => $diff) {
            $old = $diff['old'] ?? '—';
            $new = $diff['new'] ?? '—';
            $parts[] = "{$field}: {$old} → {$new}";
        }

        return implode(', ', $parts);
    }
}