<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use App\Mail\VerifyEmail;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Fields allowed to be sorted on. Centralized so it's a one-line
     * change to allow/disallow a column later.
     */
    private const SORTABLE_FIELDS = ['name', 'email', 'phone', 'status', 'user_role', 'created_at', 'id'];

    /**
     * Fields returned for list/detail views. Change once here to add
     * or remove a column from every response.
     */
    private const SELECT_FIELDS = ['id', 'name', 'email', 'phone', 'profile_url', 'user_role', 'status', 'created_at'];

    /**
     * Editable fields for the update() endpoint, mapped to the columns
     * they correspond to. Add an entry here to support editing a new
     * field from the client without touching the rest of the method.
     */
    private const EDITABLE_FIELDS = ['name', 'email', 'phone', 'status', 'user_role', 'profile_url'];

    /**
     * Display a listing of users (PUBLIC)
     */
    public function index(Request $request)
    {
        try {
            Log::info('Users API called', $request->all());

            $query = User::query();

            $this->applyRoleFilter($query, $request);
            $this->applyStatusFilter($query, $request);
            $this->applySearch($query, $request);
            $this->applySort($query, $request);

            $total = $query->count();

            $perPage = (int) $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);

            $users = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get(self::SELECT_FIELDS);

            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to = min($page * $perPage, $total);

            Log::info('Users API response', ['count' => $users->count()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $users,
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / max($perPage, 1)),
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch users', $e);
        }
    }


    /**
     * Add a new user (PUBLIC)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:50',
                'password' => 'required|string|min:8',
            ]);

            // $validated['first_name'] = bcrypt($validated['first_name']);
            // $validated['last_name'] = bcrypt($validated['last_name']);
            $validated['password'] = bcrypt($validated['password']);
            $validated['status'] = 'pending';
            $validated['user_role'] = 'user';

            // Generate verification token (raw token goes in the email,
            // hashed token is what we store/compare against)
            $verificationToken = bin2hex(random_bytes(32));
            $validated['verification_token'] = hash('sha256', $verificationToken);
            $validated['verification_token_expires_at'] = now()->addHours(24);
            $validated['email_verified_at'] = null;

            $user = User::create($validated);

            $this->logActivity(
                userId: $user->id,
                actorId: $request->user()?->id,
                action: 'user_created',
                changes: ['name' => ['old' => null, 'new' => $user->name]],
                request: $request,
            );

            // Notify the user their account is pending approval / needs verification
            try {
                Mail::to($user->email)->send(new VerifyEmail($user, $verificationToken));
                Log::info('Verification email sent to: ' . $user->email);
            } catch (\Exception $e) {
                Log::error('Failed to send pending-approval email: ' . $e->getMessage());
                // Don't fail the request just because the email didn't send
            }

            Log::info('User created', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create user', $e);
        }
    }

    /**
     * Display a single user (PUBLIC)
     */
    public function show($id)
    {
        try {
            $user = User::select(self::SELECT_FIELDS)->find($id);

            if (!$user) {
                return $this->notFoundResponse();
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user', $e, ['user_id' => $id]);
        }
    }

    /**
     * Update one or more editable fields on a user (name, email, phone,
     * status, role, profile_url). Every changed field is captured as a
     * diff and written to user_activity_logs in one entry.
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse();
            }

            $data = $request->only(self::EDITABLE_FIELDS);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No editable fields were provided.',
                ], 422);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'phone' => 'sometimes|string|max:50',
                'status' => 'sometimes|in:pending,approved',
                'user_role' => 'sometimes|in:user,admin',
                'profile_url' => 'sometimes|nullable|string|max:255',
            ]);

            $data = $validated;

            $changes = $this->buildDiff($user, $data);

            if (empty($changes)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected.',
                    'data' => $user,
                ]);
            }

            $user->update($data);

            $this->logActivity(
                userId: $user->id,
                actorId: $request->user()?->id,
                action: $this->resolveActionType($changes),
                changes: $changes,
                request: $request,
            );

            Log::info('User updated', ['user_id' => $user->id, 'changes' => $changes]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update user', $e, ['user_id' => $id]);
        }
    }

    /**
     * Update status only — kept as a separate, focused endpoint since
     * approve/reject actions are common enough to warrant their own
     * route, but it now also logs to user_activity_logs.
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse();
            }

            $oldStatus = $user->status;
            $newStatus = $request->status;

            $user->update(['status' => $newStatus]);

            if ($oldStatus !== $newStatus) {
                $this->logActivity(
                    userId: $user->id,
                    actorId: $request->user()?->id,
                    action: UserActivityLog::ACTION_STATUS_UPDATED,
                    changes: ['status' => ['old' => $oldStatus, 'new' => $newStatus]],
                    request: $request,
                );
            }

            Log::info('User status updated', [
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update status', $e, ['user_id' => $id]);
        }
    }

    /**
     * Activity log history for a single user, newest first, paginated.
     * Powers the "Activity" tab inside the user slide-out panel.
     */
    public function activity(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse();
            }

            $perPage = (int) $request->get('per_page', 20);
            $page = (int) $request->get('page', 1);

            $query = $user->activityLogs()->with('actor:id,name,email');

            $total = $query->count();

            $logs = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $logs,
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / max($perPage, 1)),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch activity log', $e, ['user_id' => $id]);
        }
    }

    // ── Query builder helpers ──────────────────────────────────
    // Each one does exactly one job, so the index() method stays
    // short and each filter can be changed independently.

    private function applyRoleFilter($query, Request $request): void
    {
        $role = $request->get('role');
        if ($role && $role !== 'all') {
            $query->where('user_role', $role);
        }
    }

    private function applyStatusFilter($query, Request $request): void
    {
        $status = $request->get('status', 'all'); // was 'approved' — pending users were hidden by default
        if ($status !== 'all') {
            $query->where('status', $status);
        }
    }

    private function applySearch($query, Request $request): void
    {
        $search = $request->get('search');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
    }

    private function applySort($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = strtolower($request->input('sort_order', 'desc'));

        $sortBy = in_array($sortBy, self::SORTABLE_FIELDS) ? $sortBy : 'created_at';
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);
    }

    // ── Logging / diff helpers ─────────────────────────────────

    /**
     * Compare incoming data against the user's current values and
     * return only the fields that actually changed, in
     * { field: { old, new } } shape.
     */
    private function buildDiff(User $user, array $data): array
    {
        $changes = [];
        foreach ($data as $field => $newValue) {
            $oldValue = $user->{$field};
            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }
        return $changes;
    }

    /**
     * Pick a single best-fit action label for a multi-field update.
     * Falls back to the generic "profile_updated" when several
     * unrelated fields changed at once.
     */
    private function resolveActionType(array $changes): string
    {
        if (count($changes) === 1) {
            return match (array_key_first($changes)) {
                'status' => UserActivityLog::ACTION_STATUS_UPDATED,
                'user_role' => UserActivityLog::ACTION_ROLE_UPDATED, // user ↔ admin
                default => UserActivityLog::ACTION_PROFILE_UPDATED,
            };
        }
        return UserActivityLog::ACTION_PROFILE_UPDATED;
    }

    private function logActivity(int $userId, ?int $actorId, string $action, array $changes, Request $request): void
    {
        UserActivityLog::record(
            userId: $userId,
            actorId: $actorId,
            action: $action,
            changes: $changes,
            ipAddress: $request->ip(),
        );
    }

    // ── Response helpers ────────────────────────────────────────

    private function notFoundResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    private function errorResponse(string $message, \Exception $e, array $context = [])
    {
        Log::error($message, array_merge($context, [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]));

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $e->getMessage(),
        ], 500);
    }
}