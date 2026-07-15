<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'phone'     => 'nullable|string|max:20',
            'subject'   => 'nullable|string|max:255',
            'message'   => 'nullable|string',
        ]);

        $contact = Contact::create($validated);

        $adminEmails = User::where('user_role', 'admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Message submitted successfully!',
            'data'    => $contact,
            'admin_emails' => $adminEmails,
        ], 201);
    }

    /**
     * List contact messages for the admin panel.
     * ?search= ?status=pending|replied|all ?per_page=
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->user_role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $perPage = (int) $request->input('per_page', 10);
        $search  = trim((string) $request->input('search', ''));
        $status  = $request->input('status', 'all');

        $query = Contact::query()->orderBy('date_created', 'desc');

        if ($status !== 'all' && in_array($status, ['pending', 'replied'])) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $contacts = $query->with('repliedByAdmin:id,name,email')->paginate($perPage);

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->user_role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $contact = Contact::with('repliedByAdmin:id,name,email')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contact]);
    }

    /**
     * Records the reply against the currently authenticated admin.
     * The actual outbound email is sent from the Next.js side (Nodemailer).
     */
    public function reply(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->user_role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $contact = Contact::findOrFail($id);
        
        $contact->update([
            'status'      => 'replied',
            'admin_reply' => $validated['message'],
            'replied_by'  => $user->id,
            'replied_at'  => now(),
        ]);

        $contact->load('repliedByAdmin:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Reply recorded.',
            'data'    => $contact,
        ]);
    }
}