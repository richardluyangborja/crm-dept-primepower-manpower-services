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
            htmlString: <<<HTML
                <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#0f172a">
                  <h2 style="margin-bottom:4px">Hi {$this->e($this->name)},</h2>
                  <p style="color:#475569">Your Primepower {$this->e($this->purposeLabel())} is:</p>
                  <p style="font-size:32px;font-weight:bold;letter-spacing:8px;margin:16px 0">{$this->e($this->code)}</p>
                  <p style="color:#475569">It expires in {$this->ttlMinutes} minutes and works once. If you didn't ask for this, just ignore it — your account stays safe.</p>
                  <p style="color:#94a3b8;font-size:12px">Primepower Manpower · CRM</p>
                </div>
                HTML,
            textString: "Hi {$this->name},\n\nYour Primepower {$this->purposeLabel()} is: {$this->code}\n\nIt expires in {$this->ttlMinutes} minutes and works once. If you didn't ask for this, just ignore it.\n\nPrimepower Manpower · CRM",
        );
    }

    protected function purposeLabel(): string
    {
        return $this->purpose === 'step_up' ? 'verification code' : 'login code';
    }

    protected function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
