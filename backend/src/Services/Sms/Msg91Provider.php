<?php

declare(strict_types=1);

namespace MediTrack\Services\Sms;

use Monolog\Logger;

class Msg91Provider implements SmsProviderInterface
{
    private string $authKey;
    private string $templateId;
    private ?Logger $logger;

    public function __construct(?string $authKey = null, ?string $templateId = null, ?Logger $logger = null)
    {
        $this->authKey = $authKey ?? ($_ENV['MSG91_AUTH_KEY'] ?? '');
        $this->templateId = $templateId ?? ($_ENV['MSG91_OTP_TEMPLATE_ID'] ?? '');
        $this->logger = $logger;
    }

    public function send(string $to, string $message): bool
    {
        // If authKey is empty or in local/test mode, fall back to logging
        if (empty($this->authKey) || ($_ENV['APP_ENV'] ?? '') === 'testing' || ($_ENV['APP_ENV'] ?? '') === 'local') {
            $logMsg = "[MSG91 MOCK] To: {$to} | Template: {$this->templateId} | Message: {$message}";
            if ($this->logger) {
                $this->logger->info($logMsg);
            }
            return true;
        }

        // Clean mobile number (strip +91 or spaces)
        $cleanPhone = preg_replace('/[^\d]/', '', $to);
        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 12) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        $payload = [
            'template_id' => $this->templateId,
            'short_url' => '0',
            'recipients' => [
                [
                    'mobiles' => '91' . $cleanPhone,
                    'otp' => $message,
                ],
            ],
        ];

        $ch = curl_init('https://control.msg91.com/api/v5/flow/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authkey: ' . $this->authKey,
            'content-type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode >= 400) {
            if ($this->logger) {
                $this->logger->error("MSG91 API error: {$error} (HTTP {$httpCode}) Response: {$response}");
            }
            return false;
        }

        return true;
    }
}

