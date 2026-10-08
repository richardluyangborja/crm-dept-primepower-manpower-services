<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One-time login/verification code. Plain-text friendly, no tracking pixels. */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $code,
        public string $purpose,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        $what = $this->purpose === 'step_up' ? 'verification code' : 'login code';

        return new Envelope(subject: "Your Primepower {$what}: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.otp-code',
            text: 'emails.otp-code-text',
            with: [
                'name' => $this->name,
                'code' => $this->code,
                'purposeLabel' => $this->purpose === 'step_up' ? 'verification code' : 'login code',
                'ttlMinutes' => $this->ttlMinutes,
            ],
        );
    }
}
