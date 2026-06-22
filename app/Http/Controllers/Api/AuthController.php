<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;
use App\Mail\ForgotPassword;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    /**
     * Register a new user and send email verification
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
                'last_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['required', 'string', 'regex:/^[0-9]{11}$/',],
                'password' => [
                    'required',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ], [

                'first_name.regex' => 'First name can only contain letters and spaces',
                'last_name.required' => 'Last name is required',
                'last_name.regex' => 'Last name can only contain letters and spaces',
                'email.required' => 'Email address is required',
                'email.email' => 'Please enter a valid email address',
                'email.unique' => 'This email address is already registered',
                'phone.required' => 'Phone number is required',
                'phone.regex' => 'Phone number must be exactly 11 digits',
                'phone.unique' => 'This phone number is already registered',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));

            $existingUser = User::where('email', strtolower(trim($request->email)))->first();
            if ($existingUser) {
                if ($existingUser->email_verified_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email address is already registered.',
                        'errors' => ['email' => ['This email address is already registered.']]
                    ], 422);
                }

                // Unverified — resend the existing token, do NOT overwrite it
                try {
                    // Regenerate a fresh token and update (resend flow)
                    $existingUser->update([
                        'verification_token' => hash('sha256', $verificationToken),
                        'verification_token_expires_at' => now()->addHours(24),
                    ]);
                    Mail::to($existingUser->email)->send(new VerifyEmail($existingUser, $verificationToken));
                } catch (\Exception $e) {
                    Log::error('Failed to resend verification: ' . $e->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'message' => 'This email is already registered but not verified. We\'ve resent the verification email — please check your inbox.',
                    'data' => [
                        'requires_verification' => true,
                    ]
                ], 200);
            }


            // Create user
            $user = (new User())->forceFill([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => strtolower(trim($request->email)),
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'user_role' => 'user',   // ← add this
                'verification_token' => hash('sha256', $verificationToken),
                'verification_token_expires_at' => now()->addHours(24),
                'email_verified_at' => null,
            ]);
            $user->save();


            // Send verification email
            try {
                Mail::to($user->email)->send(new VerifyEmail($user, $verificationToken));

                Log::info('Verification email sent to: ' . $user->email);
            } catch (\Exception $e) {
                Log::error('Failed to send verification email: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());

                // Delete user if email fails
                $user->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again later.',
                    'debug' => config('app.debug') ? $e->getMessage() : null, // Only show in debug mode
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'user_role' => $user->user_role,
                        'email_verified' => false,
                        'created_at' => now(),
                    ],
                    'requires_verification' => true,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(), // ← show real error temporarily
            ], 500);
        }
    }

    /**
     * Verify user email
     */
    public function verifyEmail($token)
    {
        try {
            $hashedToken = hash('sha256', $token);

            // Look up by token alone first (no expiry filter), so a second hit on the
            // same link — e.g. an email provider's link-scanning bot, or a user
            // double-clicking — can still be recognized instead of falling through to
            // a confusing "invalid or expired" error.
            $user = User::where('verification_token', $hashedToken)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification token. Request a new email or try logging in if you already verified.',
                ], 400);
            }

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified. You can log in now.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'user_role' => $user->user_role,
                            'email_verified' => true,
                        ],
                    ],
                ], 200);
            }

            // Not yet verified — now the expiry actually matters.
            if (!$user->verification_token_expires_at || $user->verification_token_expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your verification link has expired. Please request a new one.',
                ], 400);
            }

            $authToken = null;

            DB::transaction(function () use ($user, &$authToken) {
                $user->update([
                    'email_verified_at' => now(),
                    // Keep verification_token in place (don't null it) so a repeat hit
                    // on the same link — bots, double-clicks — matches the lookup above
                    // and gets the friendly "already verified" response instead of
                    // "invalid or expired."
                    'verification_token_expires_at' => null,
                    'status' => 'approved',
                ]);

                Log::info('Email verified successfully', ['user_id' => $user->id]);

                try {
                    $authToken = $user->createToken('auth_token')->plainTextToken;
                } catch (\Exception $e) {
                    Log::warning('Verification succeeded but auth token could not be created: ' . $e->getMessage());
                }
            });

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully! You can now login.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'user_role' => $user->user_role,
                        'email_verified' => true,
                    ],
                    'token' => $authToken,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'email'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $user = User::where('email', strtolower(trim($request->email)))->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No user found with this email address.',
                ], 404);
            }

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already verified.',
                ], 400);
            }

            $verificationToken = bin2hex(random_bytes(32)); // still generate new
            $user->update([
                'verification_token' => hash('sha256', $verificationToken),
                'verification_token_expires_at' => now()->addHours(24), // reset expiry
            ]);

            // Resend verification email
            Mail::to($user->email)->send(new VerifyEmail($user, $verificationToken));

            return response()->json([
                'success' => true,
                'message' => 'A new verification email has been sent. Any previous verification links are now invalid — please use the latest email only.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Resend verification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email.',
            ], 500);
        }
    }

    /**
     * Login user (with remember me support)
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'email'],
                'password' => ['required'],
                'remember_me' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $user = User::where('email', strtolower(trim($request->email)))->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // Check if email is verified
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email first.',
                ], 403);
            }

            if ($user->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is pending approval.',
                ], 403);
            }


            // Get remember_me value from request (default to false)
            $rememberMe = $request->input('remember_me', false);

            // Log for debugging
            Log::info('Login attempt', [
                'email' => $user->email,
                'remember_me_requested' => $rememberMe,
                'remember_me_type' => gettype($rememberMe)
            ]);

            // Generate token with appropriate name based on remember_me
            $tokenName = $rememberMe ? 'auth_token_remember' : 'auth_token';

            // Create token (expiration is handled on frontend via cookie maxAge)
            $token = $user->createToken($tokenName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'user_role' => $user->user_role,
                        'email_verified' => true,
                    ],
                    'token' => $token,
                    'remember_me' => $rememberMe,
                ]
            ], 200)->cookie(
                    'auth_token',
                    $token,
                    $rememberMe ? 43200 : 60,
                    '/',
                    null,
                    true,
                    true
                );

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
            ], 500);
        }
    }

    /**
     * Login user
     */
    // public function login(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'email' => ['required', 'email'],
    //             'password' => ['required'],
    //         ], [
    //             'email.required' => 'Email address is required',
    //             'email.email' => 'Please enter a valid email address',
    //             'password.required' => 'Password is required',
    //         ]);

    //         if ($validator->fails()) {
    //             $firstError = $validator->errors()->first();

    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $firstError,
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         // Check if user exists
    //         $user = User::where('email', strtolower(trim($request->email)))->first();

    //         if (!$user) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No account found with this email address'
    //             ], 404);
    //         }

    //         // Attempt authentication
    //         if (
    //             !Auth::attempt([
    //                 'email' => strtolower(trim($request->email)),
    //                 'password' => $request->password
    //             ])
    //         ) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Incorrect password. Please try again.',
    //                 'data' => [
    //                     'user' => [
    //                         'id' => $user->id,
    //                         'first_name' => $user->first_name,
    //                         'last_name' => $user->last_name,
    //                         'name' => $user->name,
    //                         'email' => $user->email,
    //                         'phone' => $user->phone,
    //                         'user_role' => $user->user_role,
    //                     ],
    //                 ]
    //             ], 401);
    //         }

    //         // Generate token
    //         $token = $user->createToken('auth_token')->plainTextToken;

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Login successful',
    //             'data' => [
    //                 'user' => [
    //                     'id' => $user->id,
    //                     'first_name' => $user->first_name,
    //                     'last_name' => $user->last_name,
    //                     'name' => $user->name,
    //                     'email' => $user->email,
    //                     'phone' => $user->phone,
    //                     'user_role' => $user->user_role,
    //                 ],
    //                 'token' => $token
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Login error: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred during login. Please try again.',
    //         ], 500);
    //     }
    // }


    /**
     * Send forgot password email
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'email'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $email = strtolower(trim($request->email));
            $user = User::where('email', $email)->first();

            // Don't reveal if email exists for security
            if (!$user) {
                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with this email, you will receive a password reset link.',
                ], 200);
            }

            // Generate reset token (plain text to send in email)
            $resetToken = bin2hex(random_bytes(32));

            // Store HASHED token in database
            $hashedToken = hash('sha256', $resetToken);

            $user->update([
                'reset_token' => $hashedToken,
                'reset_token_expires_at' => now()->addHour(), // 1 hour expiration
            ]);

            Log::info('Password reset token generated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_length' => strlen($resetToken),
                'hashed_token' => $hashedToken,
                'expires_at' => now()->addHour()->toDateTimeString(),
            ]);

            // Send reset password email
            try {
                Mail::to($user->email)->send(new ForgotPassword($user, $resetToken));

                Log::info('Password reset email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with this email, you will receive a password reset link.',
                ], 200);

            } catch (\Exception $e) {
                Log::error('Failed to send password reset email', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $user->id
                ]);

                // Clear the reset token since email failed
                $user->update([
                    'reset_token' => null,
                    'reset_token_expires_at' => null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send password reset email. Please contact support or try again later.',
                    'debug' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Forgot password error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => ['required', 'string'],
                'email' => ['required', 'email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ], [
                'password.required' => 'Password is required',
                'password.confirmed' => 'Password confirmation does not match',
                'password.min' => 'Password must be at least 8 characters',
            ]);

            if ($validator->fails()) {
                Log::warning('Reset password validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'email' => $request->email,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = strtolower(trim($request->email));
            $token = $request->token;

            // Hash the incoming token to compare with database
            $hashedToken = hash('sha256', $token);

            Log::info('Reset password attempt', [
                'email' => $email,
                'token_length' => strlen($token),
                'hashed_token' => $hashedToken,
            ]);

            // Find user with matching email, token, and valid expiration
            $user = User::where('email', $email)
                ->where('reset_token', $hashedToken)
                ->where('reset_token_expires_at', '>', now())
                ->first();

            if (!$user) {
                // Debug: Check if user exists with this email
                $userExists = User::where('email', $email)->first();

                if (!$userExists) {
                    Log::warning('Reset password failed - user not found', [
                        'email' => $email
                    ]);
                } else {
                    Log::warning('Reset password failed - token mismatch or expired', [
                        'email' => $email,
                        'stored_token' => $userExists->reset_token,
                        'provided_hashed_token' => $hashedToken,
                        'token_expires_at' => $userExists->reset_token_expires_at,
                        'current_time' => now()->toDateTimeString(),
                        'token_match' => $userExists->reset_token === $hashedToken,
                        'token_expired' => $userExists->reset_token_expires_at
                            ? $userExists->reset_token_expires_at <= now()
                            : true,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token. Please request a new password reset link.',
                ], 400);
            }

            // Update password and clear reset token
            $user->update([
                'password' => Hash::make($request->password),
                'reset_token' => null,
                'reset_token_expires_at' => null,
            ]);

            Log::info('Password reset successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully! You can now login with your new password.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Reset password error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'N/A',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during password reset.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }



    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
    
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ])->cookie(
                'auth_token',
                '',
                -1,     // negative minutes = expire immediately
                '/',
                null,
                true,   // secure — must match the flags used when the cookie was set in login()
                true    // httpOnly
            );
    
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
    
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Build full profile URL
            $profileUrl = null;
            if ($user->profile_url) {
                // If profile_url is already a full URL, use it as is
                if (str_starts_with($user->profile_url, 'http://') || str_starts_with($user->profile_url, 'https://')) {
                    $profileUrl = $user->profile_url;
                } else {
                    // Otherwise, construct the full URL
                    // Remove leading slash if present
                    $path = ltrim($user->profile_url, '/');
                    $profileUrl = url($path);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profile_url' => $profileUrl, // Now returns full URL like http://localhost:8000/uploads/profiles/image.jpg
                        'phone' => $user->phone,
                        'user_role' => $user->user_role,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get user error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching user data.',
            ], 500);
        }
    }

    /**
     * Check if email exists
     */
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'exists' => false,
            ], 422);
        }

        $exists = User::where('email', strtolower(trim($request->email)))->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'Email already registered' : 'Email available'
        ]);
    }

    /**
     * Check if phone exists
     */
    public function checkPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'exists' => false,
            ], 422);
        }

        $exists = User::where('phone', $request->phone)->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'Phone number already registered' : 'Phone number available'
        ]);
    }
}