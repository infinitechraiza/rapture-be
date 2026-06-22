<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $resetToken;
    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $resetToken)
    {
        $this->user = $user;
        $this->resetToken = $resetToken;
        // Point to Next.js frontend reset password page
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $this->resetUrl = $frontendUrl . '/reset-password?token=' . $resetToken . '&email=' . urlencode($user->email);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password - Vencio\'s Garden',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.forgot-password',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}