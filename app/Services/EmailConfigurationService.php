<?php

namespace App\Services;

use Illuminate\Mail\Mailer;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

class EmailConfigurationService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Create a dedicated mailer instance with user's SMTP settings.
     * This avoids race conditions when multiple queue workers run simultaneously.
     */
    public function createMailer(): Mailer
    {
        $scheme = match ($this->settings->get('email_encryption', 'tls')) {
            'tls' => 'smtps',
            'ssl' => 'smtps',
            '' => 'smtp',
            default => 'smtps',
        };

        $dsn = new Dsn(
            $scheme,
            $this->settings->get('email_host', ''),
            $this->settings->get('email_username'),
            $this->decryptPassword(),
            (int) $this->settings->get('email_port', 587)
        );

        $transport = (new EsmtpTransportFactory)->create($dsn);

        $mailer = new Mailer(
            'user-smtp',
            app('view'),
            $transport,
            app('events')
        );

        $mailer->alwaysFrom(
            $this->settings->get('email_from_address', ''),
            $this->settings->get('email_from_name', '')
        );

        return $mailer;
    }

    /**
     * Check if email is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->settings->get('email_host'))
            && ! empty($this->settings->get('email_from_address'));
    }

    /**
     * Send test email to verify configuration.
     *
     * @return array{success: bool, message: string}
     */
    public function sendTestEmail(string $toEmail): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'E-Mail-Konfiguration unvollständig. Bitte alle SMTP-Einstellungen ausfüllen.',
            ];
        }

        try {
            $mailer = $this->createMailer();
            $businessName = $this->settings->get('business_name', 'CRM');

            $mailer->raw(
                "Dies ist eine Test-E-Mail von Ihrem CRM.\n\nWenn Sie diese E-Mail erhalten haben, ist Ihre E-Mail-Konfiguration korrekt.",
                function ($message) use ($toEmail, $businessName) {
                    $message->to($toEmail)
                        ->subject("Test-E-Mail von {$businessName}");
                }
            );

            return [
                'success' => true,
                'message' => 'Test-E-Mail wurde erfolgreich gesendet.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Fehler: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Decrypt the stored password.
     */
    protected function decryptPassword(): ?string
    {
        $encrypted = $this->settings->get('email_password');

        if (empty($encrypted)) {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (\Exception) {
            // If decryption fails, treat as plain text (for migration from old format)
            return $encrypted;
        }
    }
}
